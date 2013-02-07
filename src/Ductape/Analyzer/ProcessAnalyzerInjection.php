<?php
/*
 * This file is part of DUCTAPE project.
 * 
 * Copyright (c)2013 Rafal Lindemann <rl@stamina.pl>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ductape\Analyzer;

/** Class for analyzing PHP scripts.
 *  */
class ProcessAnalyzerInjection {

    protected $options;
    protected static $instance;
    
    public static function instance() {
        if (!self::$instance) self::$instance = new static;
        return self::$instance;
    }
    
    public function start() {
        if (!isset($_SERVER["DUCTAPE"]) || !isset($_SERVER["DUCTAPE_OPTIONS"])) {
            throw \Exception("Missing BUILDER environment!");
        }
        $this->options = unserialize($_SERVER["DUCTAPE_OPTIONS"]);
        
        foreach ($this->options['globals'] as $k => $v) {
            if (isset($GLOBALS[$k]) && is_array($GLOBALS[$k])) {
                $GLOBALS[$k] = array_merge($GLOBALS[$k], $v);
            } else {
                $GLOBALS[$k] = $v;
            }
        }
        
        register_shutdown_function(array($this, 'finish'));
    }
    
    public function finish() {
        echo "\n\nScript finished here <------------------\n";
        
        $error = error_get_last();
        
        if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_COMPILE_ERROR))) {
            echo "\nScript failure detected!";
            return;
        }
        
        if ( !empty($this->options['loadClasses']) ) {
            foreach($this->options['loadClasses'] as $class) {
                if (!class_exists($class)) {
                    throw new \Exception("Additional class '{$class}' does not exist!");
                }
            }
        }
        
        $classes = get_declared_classes();
        $skipClasses = array('Ductape\\Analyzer\\ProcessAnalyzerInjection');
        $classes = array_diff($classes, $skipClasses);
        
        require_once realpath(__DIR__ . '/DependencyAnalyzer.php');
        
        $da = new DependencyAnalyzer();
        $da->analyzeClasses($classes);
        
        $results = $da->getResults();
        $results = serialize($results);
        file_put_contents($this->options['dumpFile'], $results);
    }
    
}



