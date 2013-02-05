<?php

namespace Ductape\Command;

class CommandValue {

    /** @var AbstractCommand */
    protected $command;
    protected $value;
    protected $identifier;
    protected $type;
    protected $defaultType;
    
    const TYPE_OBJECT = 'object';
    const TYPE_ARRAY = 'array';
    
    const TYPE_STRING = 'string';
    const TYPE_JSON = 'json';
    const TYPE_FILE = 'file';
    const TYPE_SET = 'set';

    
    function __construct( $command, $value, $defaultType = self::TYPE_STRING ) {
        if (is_array($value)) {
            if (!count($value)) $value = null;
            elseif (count($value) == 1) $value = $value[0];
        }
        
        $this->defaultType = $defaultType;
        $this->command = $command;
        $this->value = $value;
        
        $this->parse();
    }

    
    public function isEmpty() {
        return $this->value === null;
    }
    
    
    public function isFile() {
        return $this->type === self::TYPE_FILE;
    }
    
    
    public function isElementsSet() {
        return $this->type === self::TYPE_SET;
    }
    
    
    public function isArray() {
        return $this->type === self::TYPE_ARRAY
                || $this->type === self::TYPE_JSON
                || $this->type === self::TYPE_SET
                || $this->type === self::TYPE_OBJECT
                ;
    }
    
    
    public function getType() {
        return $this->type;
    }
    
    
    public function getRaw() {
        return $this->value;
    }

    
    public function getFilePath() {
        $value = $this->value;
        if ($this->type == self::TYPE_FILE) 
            $value = $this->identifier;
        elseif ($this->isArray()) 
            return null;
        
        if (strtolower($value) === 'stdin' || strtolower($value) === 'php://stdin') {
            $value = 'php://stdin';
        } elseif (strtolower($value) === 'stdout') {
            $value = 'php://stdout';
        } elseif (strtolower($value) === 'stderr') {
            $value = 'php://stderr';
        }
        
        return $value;
    }
    
    
    public function getFileContents() {
        $path = $this->getFilePath();
        if (!$path) return null;

        if ($path === 'php://stdin') {
            $server = $GLOBALS;
            // check if any data is available first...
            $read = array(STDIN);
            $write = array();
            $except = array();
            $result = stream_select($read, $write, $except, 0);
            if (!$result) return null;
        } 
        
        return file_get_contents($path);
    }

    
    public function getSetId() {
        if ($this->type == self::TYPE_SET) return $this->identifier;
        return null;
    }
    
    
    public function getString() {
        if ($this->type === self::TYPE_ARRAY) {
            
            $result = '';
            foreach ($this->value as $value) {
                $value = new CommandValue($this->command, $value, $this->defaultType);
                $result .= $value->getString();
            }
            return $result;
            
        } elseif ($this->type === self::TYPE_STRING || $this->type === self::TYPE_JSON) {
            return $this->value;
        } elseif ($this->type === self::TYPE_OBJECT) {
            return (string)$this->value;
        } elseif ($this->type === self::TYPE_FILE) {
            return $this->getFileContents();
        } else {
            return implode("\n", $this->getArray()) . "\n";
        }
    }
    
    
    public function getBool() {
        return $this->value == true;
    }
    
    
    public function getArray() {
        if ($this->type === self::TYPE_ARRAY) {
            
            $result = array();
            foreach ($this->value as $value) {
                $value = new CommandValue($this->command, $value, $this->defaultType);
                $result = array_merge($result, $value->getArray());
            }
            return $result;
            
        } elseif ($this->type === self::TYPE_SET) {
            
            return $this->command->getApplication()->getElements($this->getSetId());
            
        } elseif ($this->type === self::TYPE_FILE) {
            
            $value = $this->getFileContents();
            $value = (new CommandValue($this->command, $value));
            return $value->getArray();

        } elseif ($this->type === self::TYPE_JSON) {
            
            $value = json_decode($this->value, JSON_OBJECT_AS_ARRAY);
            if (json_last_error()) throw new \Exception("Json format is incorrect in: '{$this->value}'");
            return $value;
            
        } elseif ($this->type === self::TYPE_OBJECT) {
            
            return $this->value;
            
        } elseif (!$this->value) {
            return array();
        }
        return preg_split('/\r?\n/', $this->value, -1, PREG_SPLIT_NO_EMPTY);
    }
    
    
    protected function parse() {
        $value = $this->value;
        if (is_string($this->value)) $this->identifier = $this->value;
        
        $this->type = $this->defaultType;
        
        if (is_array($value)) {
            $this->type = isset($value[0]) ? self::TYPE_ARRAY : self::TYPE_OBJECT;
            return;
        }
        
        if (!is_string($value) || strlen($value) < 3) {
            return;
        }
        
        $first = $value[0];
        $last = substr($value, -1);
        
        if ($first === '#' && $last === '#' && strpos($value, "\n") === false) {
            
            $this->type = self::TYPE_FILE;
            $this->identifier = substr($value, 1, -1);
            
        } elseif ($first === '$' && $last === '$' && strpos($value, "\n") === false) {
            
            $this->type = self::TYPE_SET;
            $this->identifier = substr($value, 1, -1);
            
        } elseif (($first === '{' && $last === '}') || ($first === '[' && $last === ']')) {
            
            $this->type = self::TYPE_JSON;
            
        }
    }
    
}