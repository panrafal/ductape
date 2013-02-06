<?php

namespace Ductape\Analyzer;

/** Analyzes dependencies between classes and files */
class DependencyAnalyzer {

    /** Set to TRUE to include built-in and extension's classes */
    public $includeBuiltin = false;
    
    protected $classes = array();
    protected $files = array();
    
    public function analyzeClasses($classes, $filter = null) {
        if (!$classes) return false;
        foreach($classes as $class) {
            $this->analyzeClass($class, $filter);
        }
        
    }
    
    public function analyzeClass($class, $filter = null) {
        if (!$class) return false;
        if ($class instanceof \ReflectionClass == false) {
            if (isset($this->classes[$class])) return false;
            $class = new \ReflectionClass($class);
        } else {
            if (isset($this->classes[$class->name])) return false;
        }
        
        if ($this->includeBuiltin == false && $class->getFileName() == false) return false;
        
        if ($filter && $filter($class) == false) return false;
        
        $this->analyzeClass($class->getParentClass());
        $this->analyzeClasses($class->getInterfaces(), $filter);
        $this->analyzeClasses($class->getTraits(), $filter);

        $this->classes[$class->name] = $class;
        $file = $class->getFileName();
        if ($file && $file != '-' && !isset($this->files[$file])) $this->files[$file] = true;
        
        return true;
    }    
    
    /**
     * Returns all collected classes in order of inheritance
     * 
     * @param callable $filter Filter closure.
     */
    public function getClasses($filter = null) {
        if ($filter) {
            return array_filter($this->classes, $filter);
        }
        return $this->classes;
    }
    
    /**
     * Returns all collected php files in order of inheritance
     * 
     * @param callable $filter Filter closure.
     */
    public function getFiles($filter = null, $normalize = false) {
        $files = array_keys($this->files);
        if ($normalize) {
            $files = array_map(function($file) {
                if ($file[1] == ':') $file = substr($file, 2);
                return strtr($file, '\\', '/');
            }, $files);
        }
        if ($filter) {
            return array_filter($files, $filter);
        }
        return $files;
    }
    
    /** Analyzes current environment 
     * @return self */
    public function analyzeCurrent($filter = null) {
        $this->analyzeClasses(get_declared_classes(), $filter);
        return $this;
    }
    
    /**  */
    public function getResults() {
        $result = array(
            'files' => array(),
            'classes' => array()
        );
        foreach($this->getClasses() as $class) {
            /* @var $class \ReflectionClass */
            $result['classes'][$class->name] = $class->getFileName();
        }
        $result['files'] = $this->getFiles();
        return $result;
    }
    
}
    