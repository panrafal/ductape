<?php

/**
 * Copyright (c)2013 Rafal Lindemann
 * 
 * Greatly inspired by JuggleCode by Codeless (http://www.codeless.at/).
 */

namespace Ductape\Parser;

use Chequer;
use Ductape\Utility\FileHelper;
use Exception;
use PHPParser_Lexer;
use PHPParser_Node_Expr_Include;
use PHPParser_Node_Scalar_DirConst;
use PHPParser_Node_Scalar_FileConst;
use PHPParser_Node_Stmt_Namespace;
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
    protected $parseStack = array();
    protected $includedFiles = array();

    
    /**
     * @param $combineFiles Array of files to combine.
     */
    public function __construct( $combineFiles ) {
        parent::__construct();
        foreach($combineFiles as $file) {
            if (!is_file($file)) throw new Exception("File '$file' does not exist!");
            $this->combineFiles[] = realpath($file);
        }
        $this->includedFiles = $this->combineFiles;
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


    public function combine( $outputFile = false ) {

        if ($this->baseDir == false) $this->baseDir = $outputFile ? dirname($outputFile) : getcwd();
        $this->baseDir = realpath($this->baseDir);

        $code = '<?php' . PHP_EOL;

        foreach ($this->combineFiles as $file) {
            $code .= $this->parseFile($file);
        }

        if ($outputFile) {
            file_put_contents($outputFile, $code);
        } else {
            return $code;
        }
    }


    protected function parseFile( $file ) {
        if (!is_file($file)) throw new Exception("File '$file' not found!");

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
        array_push($this->parseStack, array(
            'file' => $file,
            'tree' => $syntaxTree
        ));
        
        // Convert syntax tree back to PHP statements:
        $compiled = $this->prettyPrint($syntaxTree);

        array_pop($this->parseStack);

        return $compiled;
    }


    protected function &getCurrentlyParsed($node = null) {
        if ($node) {
            return $this->parseStack[count($this->parseStack) - 1][$node];
        } else {
            return $this->parseStack[count($this->parseStack) - 1];
        }
    }

    
    protected function getCurrentlyParsedFile() {
        return $this->getCurrentlyParsed('file');
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
            $currentFile = $this->getCurrentlyParsedFile();
            $file = ParserHelper::resolveNodeValue($node->expr, array(
                '__DIR__' => dirname($currentFile),
                '__FILE__' => $currentFile
            ));
            
            if (!$file || !is_file($file)) {
                if ($this->allowMissingIncludes) {
                    return parent::pExpr_Include($node);
                } else {
                    throw new Exception(sprintf("Include file '%s' not found in %s @ %d", $file, $this->getCurrentlyParsedFile(), $node->getLine()));
                }
            }
            
            $file = realpath($file);

            if (in_array($file, $this->includedFiles)) {
                // protection from ; suffixed by pretty printer
                return '//';
            }
            
            // check where we are in the node tree
            $syntaxTree = $this->getCurrentlyParsed('tree');
            
            // first nodes are guaranteed to be namespaces
            $namespace = false;
            foreach($syntaxTree as $namespaceNode) {
                if ($namespaceNode instanceof \PHPParser_Node_Stmt_Namespace == false)
                    throw new Exception("Namespace was expected, " . $namespaceNode->getType() . " was found");
                
                if (in_array($node, $namespaceNode->stmts, true)) {
                    $namespace = $namespaceNode;
                    break;
                }
            }
            if ($namespace) {
                if (!$this->includesFilter || $this->includesFilter($file)) {
                    // if it passed the filter, let's rock!
                    $this->includedFiles[] = $file;
                    
                    // close current namespace...
                    $code = "}\n/* include_once ".basename($file)." */\n";
                    $code .= $this->parseFile($file);
                    
                    // reopen the namespace
                    $code .= "\nnamespace" . (null !== $namespace->name ? ' ' . $this->p($namespace->name) : '') . " {";
                    // protection from ; suffixed by pretty printer
                    $code .= '//';
                    return $code;
                }
            } else {
                // we shall leave it intact
            }
            
        }
                
        return parent::pExpr_Include($node);
    }


    public function pStmt_Namespace( PHPParser_Node_Stmt_Namespace $node ) {
        return parent::pStmt_Namespace($node);
    }


    public function pScalar_DirConst( PHPParser_Node_Scalar_DirConst $node ) {
        $path = FileHelper::relativePath($this->baseDir, dirname($this->getCurrentlyParsedFile()), '/');
        if ($path) $path = '/' . $path;
        return "__DIR__ . '" . addslashes($path) . "' /*__DIR__*/";
    }


    public function pScalar_FileConst( PHPParser_Node_Scalar_FileConst $node ) {
        $path = FileHelper::relativePath($this->baseDir, $this->getCurrentlyParsedFile(), '/');
        if ($path) $path = '/' . $path;
        return "__DIR__ . '" . addslashes($path) . "' /*__FILE__*/";
    }


}


