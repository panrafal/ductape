<?php
/*
 * This file is part of DUCTAPE project.
 * 
 * Copyright (c)2013 Rafal Lindemann <rl@stamina.pl>
 * 
 * Greatly inspired by JuggleCode by Codeless (http://www.codeless.at/).
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ductape\Parser;

use Chequer;
use Ductape\Utility\FileHelper;
use Exception;
use PHPParser_Lexer;
use PHPParser_Node;
use PHPParser_Node_Expr_ClassConstFetch;
use PHPParser_Node_Expr_Include;
use PHPParser_Node_Expr_New;
use PHPParser_Node_Expr_StaticCall;
use PHPParser_Node_Expr_StaticPropertyFetch;
use PHPParser_Node_Name;
use PHPParser_Node_Scalar_DirConst;
use PHPParser_Node_Scalar_FileConst;
use PHPParser_Node_Stmt_Class;
use PHPParser_Node_Stmt_Interface;
use PHPParser_Node_Stmt_Namespace;
use PHPParser_Node_Stmt_Trait;
use PHPParser_Node_Stmt_TraitUse;
use PHPParser_Node_Stmt_UseUse;
use PHPParser_Parser;
use PHPParser_PrettyPrinter_Zend;

/**
 * Combines multiple source files into one.
 * 
 * Allows resolving of require_once and include_once statements. If allowed, they will also
 * be parsed into the source coded.
 * 
 * All filenames are resolved, and proper relative paths are ensured by substituting __DIR__ and
 * __FILE__ constants.
 * 
 * <b>Combiner</b> easely understands statements like:
 * <code>
 * require_once __DIR__ . '/test_file1.php';
 * require_once dirname(__FILE__) . '/' . 'test_file1.php';
 * </code>
 * 
 * <b>Word of caution!</b>
 * 
 * This class works by parsing your sources into syntax tree, and then putting
 * it back together. It generally works great, but your mileage may vary. Always test your code
 * afterwards!
 * 
 * Parsing is provided by the great GREAT work of PHPParser team. 
 * 
 */
class SourceCombiner extends PHPParser_PrettyPrinter_Zend {

    protected $comments = true;
    protected $combineFiles = array();
    protected $includesFilter = null;
    protected $allowMissingIncludes = true;
    protected $baseDir = null;

    protected $currentFile = null;
    protected $currentSyntaxTree = null;
    /** @var PHPParser_Node_Stmt_Namespace */
    protected $currentNamespace = null;
    protected $currentUsemap = array();
    
    /** class => file */
    protected $classmap = array();
    /* file => [ code => , classes => needed , files => needed ] */
    protected $parsedFiles = array();
    protected $unknownClasses = array();
    
    /**
     * @param $combineFiles Array of files to combine.
     */
    public function __construct( $combineFiles ) {
        parent::__construct();
        foreach($combineFiles as $file) {
            if (!is_file($file)) throw new Exception("File '$file' does not exist!");
            $this->combineFiles[] = realpath($file);
        }
    }


    /**
     * TRUE to leave comments, or FALSE to strip them out.
     * 
     * Defaults to true
     * 
     * @return self 
     */
    public function setComments( $comments ) {
        $this->comments = $comments;
        return $this;
    }

    
    /**
     * TRUE to leave require_once/include_once with missing files intact, or FALSE to
     * throw an exception.
     * 
     * Defaults to true
     * 
     * @return self
     */
    public function setAllowMissingIncludes($allowMissingIncludes) {
        $this->allowMissingIncludes = $allowMissingIncludes;
        return $this;
    }

    
    /**
     * Callback filter for require_once and include_once statements inside of combined sources.
     * 
     * Should return TRUE to include specified file, or FALSE to exclude it.
     * 
     * You can also pass an array in Chequer Query Language, or FALSE to disable inclusion of all files.
     * 
     * @return self */
    public function setIncludesFilter( $includesFilter ) {
        $this->includesFilter = is_callable($includesFilter) ? $includesFilter : new Chequer($includesFilter);
        return $this;
    }


    /**
     * Base dir to use for relative paths in __DIR__ and __FILE__ substitutions.
     * 
     * Defaults to directory of the output file.
     * 
     * @return self */
    public function setBaseDir( $baseDir ) {
        $this->baseDir = $baseDir;
        return $this;
    }


    /** Returns classmap as [classname => filepath, ...] */
    public function getClassmap() {
        return $this->classmap;
    }


    /** Returns information about parsed files as 
     * [filepath => [ 
     *      code => source code
     *      classes => [[classname => count first level references], ...]
     *      files => [[filepath => count references], ...]
     * ],
     *  ...] */
    public function getParsedFilesInfo() {
        return $this->parsedFiles;
    }


    /** Returns unknown classes as [class => count of dependant files] */
    public function getUnknownClasses() {
        return $this->unknownClasses;
    }


    public function combine( $outputFile = false ) {

        if ($this->baseDir == false) $this->baseDir = $outputFile ? dirname($outputFile) : getcwd();
        $this->baseDir = realpath($this->baseDir);

        $code = '<?php' . PHP_EOL;

        // make the first run
        while ($this->combineFiles) {
            $file = array_shift($this->combineFiles);
            $this->parseFile($file);
        }

        // resolve classes into files
        $this->resolveParsedClassnames();
        
        // fold back the code using dependency
        $foldedFiles = array();
        foreach($this->parsedFiles as $file => $info) {
            $this->foldbackCode($file, $code, $foldedFiles);
        }
        $this->parsedFiles = $foldedFiles;
        
        if ($outputFile) {
            file_put_contents($outputFile, $code);
        } else {
            return $code;
        }
    }

    
    protected function parseFile( $file ) {
        if ($this->currentFile) throw new Exception('Only one file at a time!');
        if (!is_file($file)) throw new Exception("File '$file' not found!");

        if (isset($this->parsedFiles[$file])) return $this->parsedFiles[$file]['code'];

        // initialize the file's table
        $this->parsedFiles[$file] = array(
            'code' => false,
            'classes' => array(),
            'files' => array()
        );
        
        $code = file_get_contents($file);

        // Create parser
        $parser = new PHPParser_Parser(new PHPParser_Lexer);

        // Parse
        $syntaxTree = $parser->parse($code);

        // Ensure that we always have everything wrapped in a namespace
        if ($syntaxTree 
                && $syntaxTree[0] instanceof PHPParser_Node_Stmt_Namespace == false
        ) {
            $syntaxTree = array(new PHPParser_Node_Stmt_Namespace(null, $syntaxTree));
        }

        // keep track of currently parsed file
        $this->currentFile = $file;
        $this->currentSyntaxTree = $syntaxTree;
        
        // Convert syntax tree back to PHP statements:
        $compiled = $this->prettyPrint($syntaxTree);
        
        $this->parsedFiles[$file]['code'] = $compiled;

        $this->currentFile = $this->currentSyntaxTree = null;

        return $compiled;
    }

    
    protected function resolveParsedClassnames() {
        // for every parsed file and every dependent class...
        foreach($this->parsedFiles as $file => &$info) {
            foreach($info['classes'] as $class => $count) {
                if (isset($this->classmap[$class])) {
                    // make it into dependent file...
                    $this->incrementSubkey($info['files'], $this->classmap[$class]);
                } else {
                    // or remember and not forgive!
                    $this->incrementSubkey($this->unknownClasses, $class);
                }
            }
        }
    }


    protected function foldbackCode($file, &$code, &$foldedFiles) {
        if (isset($foldedFiles[$file])) return;
        $foldedFiles[$file] = $this->parsedFiles[$file];
        
        // fold parents first!
        foreach($this->parsedFiles[$file]['files'] as $parentFile => $count) {
            $this->foldbackCode($parentFile, $code, $foldedFiles);
        }
        
        $code .= $this->parsedFiles[$file]['code'] . "\n";
    }
    

    protected function incrementSubkey(&$array, $key, $inc = 1) {
        if (isset($array[$key])) {
            $array[$key] += $inc;
        } else {
            $array[$key] = $inc;
        }
    }
    
    
    /* -- printer functions --------------------------------------------------------------- */

    
    public function pComments( array $comments ) {
        if ($this->comments) {
            $comments = parent::pComments($comments);
        } else {
            $comments = null;
        }

        return $comments;
    }

    
    public function pExpr_Include( PHPParser_Node_Expr_Include $node ) {
        if ( 
                $node->type == PHPParser_Node_Expr_Include::TYPE_INCLUDE_ONCE 
             || $node->type == PHPParser_Node_Expr_Include::TYPE_REQUIRE_ONCE
        ) {
            $currentFile = $this->currentFile;
            $file = ParserHelper::resolveNodeValue($node->expr, array(
                '__DIR__' => dirname($currentFile),
                '__FILE__' => $currentFile
            ));
            
            if (!$file || !is_file($file)) {
                if ($this->allowMissingIncludes) {
                    return parent::pExpr_Include($node);
                } else {
                    throw new Exception(sprintf("Include file '%s' not found in %s @ %d", $file, $this->currentFile, $node->getLine()));
                }
            }
            
            $file = realpath($file);

            /* we remove the include if it was or will be parsed, or the filter allows for it... */
            if (isset($this->parsedFiles[$file]) || in_array($file, $this->combineFiles) || !$this->includesFilter || $this->includesFilter($file)) {

                // mark current file as dependent if on first level
                if ($this->isNodeOnFirstLevel($node)) {
                    $this->incrementSubkey($this->parsedFiles[$this->currentFile]['files'], $file);
                }
                
                // parse it, if not done already...
                if (!isset($this->parsedFiles[$file]) && !in_array($file, $this->combineFiles)) {
                    $this->combineFiles[] = $file;
                }
                
                // protection from ; suffixed by pretty printer
                return '//';
            }
            
        }
                
        return parent::pExpr_Include($node);
    }


    public function pScalar_DirConst( PHPParser_Node_Scalar_DirConst $node ) {
        $path = FileHelper::relativePath($this->baseDir, dirname($this->currentFile), '/');
        if ($path) $path = '/' . $path;
        return "__DIR__ . '" . addslashes($path) . "' /*__DIR__*/";
    }


    public function pScalar_FileConst( PHPParser_Node_Scalar_FileConst $node ) {
        $path = FileHelper::relativePath($this->baseDir, $this->currentFile, '/');
        if ($path) $path = '/' . $path;
        return "__DIR__ . '" . addslashes($path) . "' /*__FILE__*/";
    }

    
    protected function resolveClassName($node) {
        if ($node instanceof PHPParser_Node_Name) {
            /* @var $node PHPParser_Node_Name */
            if ($node->isFullyQualified()) return (string)$node;
            $node = $node->parts;
        } else {
            $node = (array)$node;
        }
        if (!is_scalar($node[0])) {
            throw new \Exception("Something is wrong with the node name!");
        }
        // check uses
        if (isset($this->currentUsemap[$node[0]])) {
            $node[0] = $this->currentUsemap[$node[0]];
            return implode('\\', $node);
        } else {
            // it's relative to current...
            return ($this->currentNamespace->name ? $this->currentNamespace->name . '\\' : '')
                . implode('\\', $node);
            ;
        }
    }
    
    
    protected function addDependentClass($node) {
        $this->incrementSubkey($this->parsedFiles[$this->currentFile]['classes'], $this->resolveClassName($node));
    }
    
    
    protected function addClass($node) {
        $this->classmap[$this->resolveClassName($node)] = $this->currentFile;
    }


    /** @return \PHPParser_Node_Stmt_Namespace */
    protected function isNodeOnFirstLevel(PHPParser_Node $node) {
        if ($this->currentNamespace) {
            if (in_array($node, $this->currentNamespace->stmts, true)) {
                return $this->currentNamespace;
            }
        }
        return null;
    }
    

    public function pStmt_Namespace( PHPParser_Node_Stmt_Namespace $node ) {
        $this->currentNamespace = $node;
        $this->currentUsemap = array();
        $code = parent::pStmt_Namespace($node);
        $this->currentNamespace = null;
        $this->currentUsemap = array();
        return $code;
    }


    public function pStmt_UseUse( PHPParser_Node_Stmt_UseUse $node ) {
        $this->currentUsemap[$node->alias ? $node->alias : $node->name->getLast()] = (string) $node->name;
        return parent::pStmt_UseUse($node);
    }


    public function pStmt_Class( PHPParser_Node_Stmt_Class $node ) {
        $this->addClass($node->name);
        if ($node->extends) $this->addDependentClass($node->extends);
        foreach ($node->implements as $name) {
            $this->addDependentClass($name);
        }
        return parent::pStmt_Class($node);
    }


    public function pStmt_Interface( PHPParser_Node_Stmt_Interface $node ) {
        $this->addClass($node->name);
        foreach ($node->extends as $name) {
            $this->addDependentClass($name);
        }
        return parent::pStmt_Interface($node);
    }


    public function pStmt_Trait( PHPParser_Node_Stmt_Trait $node ) {
        $this->addClass($node->name);
        return parent::pStmt_Trait($node);
    }


    public function pStmt_TraitUse( PHPParser_Node_Stmt_TraitUse $node ) {
        foreach ($node->traits as $name) {
            $this->addDependentClass($name);
        }
        return parent::pStmt_TraitUse($node);
    }


    public function pExpr_ClassConstFetch( PHPParser_Node_Expr_ClassConstFetch $node ) {
        if ($this->isNodeOnFirstLevel($node)) {
            $this->addDependentClass($node->class);
        }
        return parent::pExpr_ClassConstFetch($node);
    }


    public function pExpr_New( PHPParser_Node_Expr_New $node ) {
        if ($this->isNodeOnFirstLevel($node)) {
            $this->addDependentClass($node->class);
        }
        return parent::pExpr_New($node);
    }


    public function pExpr_StaticCall( PHPParser_Node_Expr_StaticCall $node ) {
        if ($this->isNodeOnFirstLevel($node)) {
            $this->addDependentClass($node->class);
        }
        return parent::pExpr_StaticCall($node);
    }


    public function pExpr_StaticPropertyFetch( PHPParser_Node_Expr_StaticPropertyFetch $node ) {
        if ($this->isNodeOnFirstLevel($node)) {
            $this->addDependentClass($node->class);
        }
        return parent::pExpr_StaticPropertyFetch($node);
    }


}


