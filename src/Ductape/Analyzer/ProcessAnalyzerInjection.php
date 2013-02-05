<?php

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
        if (!isset($_SERVER["PHP_BUILDER"]) || !isset($_SERVER["PHP_BUILDER_OPTIONS"])) {
            throw \Exception("Missing BUILDER environment!");
        }
        $this->options = unserialize($_SERVER["PHP_BUILDER_OPTIONS"]);
        
        foreach ($this->options['globals'] as $k => $v) {
            if (isset($GLOBALS[$k]) && is_array($GLOBALS[$k])) {
                $GLOBALS[$k] = array_merge($GLOBALS[$k], $v);
            } else {
                $GLOBALS[$k] = $v;
            }
        }
        
        register_shutdown_function(array($this, 'finish'));
//        ob_start();
    }
    
    public function finish() {
//        ob_end_clean();
        echo "\n\nScript finished here <------------------\n";
        if ( !empty($this->options['loadClasses']) ) {
            foreach($this->options['loadClasses'] as $class) {
                if (!class_exists($class)) {
                    throw new \Exception("Additional class {$class} does not exist!");
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



