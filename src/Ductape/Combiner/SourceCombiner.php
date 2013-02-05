<?php

/**
 * Copyright (c)2013 Rafal Lindemann
 * 
 * Greatly inspired by JuggleCode by Codeless (http://www.codeless.at/).
 */

namespace Ductape\Combiner;

use PHPParser_Lexer;
use PHPParser_Node_Expr_Include;
use PHPParser_Node_Scalar_DirConst;
use PHPParser_Node_Scalar_FileConst;
use PHPParser_Node_Stmt_Namespace;
use PHPParser_Parser;
use PHPParser_PrettyPrinter_Zend;

class Combiner extends PHPParser_PrettyPrinter_Zend {

    protected $comments = true;
    protected $combineFiles = array();
    protected $includesFilter = null;
    protected $baseDir = null;
    protected $filesStack = array();
    protected $includedFiles = array();

    public function __construct( $combineFiles ) {
        parent::__construct();
        $combineFiles = aray_map('realpath', $combineFiles);
        foreach($combineFiles as $file) {
            if (!is_file($file)) throw new \Exception("File '$file' does not exist!");
            $this->combineFiles[] = realpath($file);
        }
        $this->includedFiles = $this->combineFiles;
    }


    /**
     * TRUE to leave comments, or FALSE to strip them out.
     * 
     * @return self */
    public function setComments( $comments ) {
        $this->comments = $comments;
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
        $this->includesFilter = is_callable($includesFilter) ? $includesFilter : new \Chequer($includesFilter);
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

        if ($this->outfile) {
            file_put_contents($outputFile, $code);
        } else {
            echo $code;
        }
    }


    protected function parseFile( $file ) {
        // keep track of currently parsed file
        array_push($this->filesStack, $file);

        if (!is_file($file)) throw new \Exception("File '$file' not found!");

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

        // Convert syntax tree back to PHP statements:
        $compiled = $this->prettyPrint($syntaxTree);

        array_pop($this->filesStack);

        return $compiled;
    }


    protected function getCurrentlyParsedFile() {
        return $this->filesStack[count($this->filesStack) - 1];
    }

    
    /* -- printer functions --------------------------------------------------------------- */

    
    protected function pComments( array $comments ) {
        if ($this->comments) {
            $comments = parent::pComments($comments);
        } else {
            $comments = null;
        }

        return $comments;
    }


    protected function pExpr_Include( PHPParser_Node_Expr_Include $node ) {
        if ( 
                $node->type == PHPParser_Node_Expr_Include::TYPE_INCLUDE_ONCE 
             || $node->type == PHPParser_Node_Expr_Include::TYPE_REQUIRE_ONCE
        ) {
            $file = realpath($node->expr->value);
            
            if (!$file || !is_file($file)) {
                throw new \Exception(sprintf("Include file '%s' not found in %s @ %d", $file, $this->getCurrentlyParsedFile(), $node->getLine()));
            }

            if (in_array($file, $this->includedFiles)) {
                // already included...
                return null;
            }
            
            if (!$this->includesFilter || $this->includesFilter($file)) {
                $this->includedFiles[] = $file;
                return $this->parseFile($file);
            }
            
        }
                
        return parent::pExpr_Include($node);
    }


    protected function pStmt_Namespace( PHPParser_Node_Stmt_Namespace $node ) {
        // protection from possible ';' after namespace
        return parent::pStmt_Namespace($node) . '//';
    }


    protected function pScalar_DirConst( PHPParser_Node_Scalar_DirConst $node ) {
        return "'" . addslashes(dirname($this->getCurrentlyParsedFile())) . "' /*__DIR__*/";
    }


    protected function pScalar_FileConst( PHPParser_Node_Scalar_FileConst $node ) {
        return "'" . addslashes($this->getCurrentlyParsedFile()) . "' /*__FILE__*/";
    }


}


