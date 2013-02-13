<?php
/*
 * This file is part of DUCTAPE project.
 * 
 * Copyright (c)2013 Rafal Lindemann <rl@stamina.pl>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ductape\Command;

use Chequer;
use Ductape\Chequer\DuctapeWrapper;
use Ductape\Ductape;
use Exception;
use Symfony\Component\Console\Command\Command;

class CommandValue {

    /** @var Ductape */
    protected $ductape;
    /** @var Command */
    protected $command;
    /** @var \Symfony\Component\Console\Input\InputInterface */
    protected $input;
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
    const TYPE_CHEQUER = 'chequer';

    const CH_FILE = '#';
    const CH_SET = '@';
    
    /** @return CommandValue */
    public static function ensure($ductape, $value) {
        if ($value instanceof CommandValue) return $value;
        return new CommandValue($ductape, $value);
    }
    
    /**
     * @param $ductape
     * @param $value Value to parse
     * @param $defaultType - Default type to assume for strings - string, file, set or json
     * @param $allowArray - TRUE to enable recursive parsing of arrays (not hashmaps!)
     */
    function __construct( $ductape, $value, $defaultType = self::TYPE_STRING, $allowArray = false, $input = null ) {
        if ($allowArray && is_array($value)) {
            if (!count($value)) $value = null;
            elseif (count($value) == 1) $value = $value[0];
        }
        
        $this->defaultType = $defaultType;
        $this->command = $ductape instanceof Command ? $ductape : null;
        $this->ductape = $ductape instanceof Command ? $ductape->getApplication() : $ductape;
        $this->value = $value;
        $this->input = $input;
        
        $this->parse($allowArray);
    }
    
    /** @return CommandValue */
    public static function createRaw( $ductape, $value, $type = self::TYPE_STRING, $identifier = null ) {
        $cv = new CommandValue($ductape, null, $type);
        $cv->value = $value;
        $cv->identifier = ($identifier === null && $type === self::TYPE_STRING && is_string($value)) 
                ? $value
                : $identifier
            ;
        return $cv;
    }

    /** @return CommandValue */
    public static function createSetId( $ductape, $setId ) {
        return self::createRaw($ductape, self::CH_SET . $setId . self::CH_SET, self::TYPE_SET, $setId);
    }

    
    public function isEmpty() {
        return $this->value === null;
    }
    
    /** Returns TRUE if it's empty, assuming it might be a file... */
    public function isEmptyAsFile() {
        return $this->isEmpty() || ($this->isArray() == false && $this->asFilePath() == false);
    }
    
    
    public function isFile() {
        return $this->type === self::TYPE_FILE;
    }
    
    
    public function isElementsSet() {
        return $this->type === self::TYPE_SET;
    }
    
    
    public function isChequer() {
        return $this->type === self::TYPE_CHEQUER;
    }
    
    
    public function isArray() {
        return $this->type === self::TYPE_ARRAY
                || $this->type === self::TYPE_JSON
                || $this->type === self::TYPE_SET
                || $this->type === self::TYPE_OBJECT
                || $this->type === self::TYPE_CHEQUER
                ;
    }
    
    
    public function getType() {
        return $this->type;
    }
    
    
    public function getRaw() {
        return $this->value;
    }

    /** Returns value as a Chequer query 
     * @return Chequer
     */
    public function asChequer() {
        if ($this->isEmpty()) return null;
        if ($this->type == self::TYPE_CHEQUER) {
            $value = $this->value;
        } elseif ($this->isArray()) {
            $value = $this->asArray();
        } elseif (preg_match('/^true|false|null|[-\d.]+$/i', $this->value)) {
            $value = json_decode($this->value);
        } else {
            $value = $this->asString();
        }
        return Chequer::create($value)
                ->addTypecast('ductape', new DuctapeWrapper( $this->ductape, $this->command, $this->input ))
                ; 
    }
    
    public function asFilePath() {
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
        } elseif ($value === "-" || $value === "/dev/null") {
            return null;
        }
        
        return $value;
    }
    
    /** Returns file contents */
    public function readFileContents() {
        $path = $this->asFilePath();
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

    /** Parses file contents according to filetype
     * @return CommandValue
     *  */
    public function decodeFileContents() {
        $path = $this->asFilePath();
        if (!$path) return new CommandValue($this->ductape, null);
        
        $extension = strtolower(substr(strrchr($path, '.'), 1));
        switch($extension) {
            case 'php':
                if (strpos($path, ':') === false) {
                    // only if it's not external!
                    $data = require $path;
                    return CommandValue::createRaw( $this->ductape, $this->readFileContents()
                            , is_scalar($data) ? self::TYPE_STRING : self::TYPE_OBJECT );
                }
                break;
            case 'json':
                // treat as JSON always
                return CommandValue::createRaw( $this->ductape, $this->readFileContents(), self::TYPE_JSON );
                break;
            case 'txt':
                // treat as plain text (no json)
                return CommandValue::createRaw( $this->ductape, $this->readFileContents() );
                break;
        }

        // parse the contents normally...
        return new CommandValue( $this->ductape, $this->readFileContents() );
    }

    public function storeFileContents($data) {
        $path = $this->asFilePath();
        if (!$path) return null;

        file_put_contents($path, (string)$data);
    }
    
    public function encodeFileContents($data) {
        $path = $this->asFilePath();
        if (!$path) return null;
        
        $extension = strtolower(substr(strrchr($path, '.'), 1));
        $dataString = '';
        switch($extension) {
            case 'php':
                $dataString = '<?php return ' . var_export($data, true) . ';';
                break;
            case 'json':
                $dataString = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                break;
            default:
                if (is_string($data)) {
                    $dataString = $data;
                } else {
                    if (!count($data) || isset($data[0])) {
                        // probably an array. store line-by-line
                        $dataString = implode("\n", $data);
                    } else {
                        // everything else store as JSON
                        $dataString = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    }
                }
                
        }        
        
        $this->storeFileContents($dataString);
        return true;
    }
    
    
    public function asDatasetId() {
        if ($this->type == self::TYPE_SET) return $this->identifier;
        return null;
    }
    
    /** Returns value as provided type */
    public function asType($type) {
        switch($type) {
            case self::TYPE_STRING:
                return $this->asString();
            case self::TYPE_ARRAY:
                return $this->asArray();
            case 'content':
                return $this->getContent();
            case 'bool':
                return $this->asBool();
        }
        return $this->asString();
    }
    
    /* Returns contents as string.  */
    public function asString() {
        if ($this->type === self::TYPE_ARRAY) {
            
            $result = '';
            foreach ($this->value as $value) {
                $value = new CommandValue($this->ductape, $value, $this->defaultType, false);
                $result .= $value->asString();
            }
            return $result;
            
        } elseif ($this->type === self::TYPE_STRING || $this->type === self::TYPE_JSON) {
            return $this->value;
        } elseif ($this->type === self::TYPE_CHEQUER) {
            return $this->asChequer()->evaluate(self::TYPE_STRING);
        } elseif ($this->type === self::TYPE_OBJECT) {
            if (is_array($this->value)) return implode("\n", $this->value);
            return (string)$this->value;
        } elseif ($this->type === self::TYPE_FILE) {
            return $this->decodeFileContents()->asString();
        } else {
            return implode("\n", $this->asArray()) . "\n";
        }
    }
    
    /* Returns contents as string.  */
    public function getContent() {
        if ($this->type === self::TYPE_ARRAY) {
            
            $result = '';
            foreach ($this->value as $value) {
                $value = new CommandValue($this->ductape, $value, $this->defaultType, false);
                $result .= $value->getContent();
            }
            return $result;
            
        } elseif ($this->type === self::TYPE_STRING || $this->type === self::TYPE_JSON) {
            return $this->value;
        } elseif ($this->type === self::TYPE_CHEQUER) {
            return $this->asChequer()->evaluate('content');
        } elseif ($this->type === self::TYPE_OBJECT) {
            return $this->value;
        } elseif ($this->type === self::TYPE_FILE) {
            return $this->readFileContents();
        } else {
            return $this->asArray();
        }
    }
    
    
    public function asBool() {
        if ($this->type === self::TYPE_CHEQUER) {
            return $this->asChequer()->evaluate('bool') == true;
        }
        if (is_string($this->value)) {
            if (strcasecmp($this->value, 'false') === 0) return false;
            if (strcasecmp($this->value, 'not') === 0) return false;
            if (strcasecmp($this->value, 'no') === 0) return false;
            if (strcasecmp($this->value, 'off') === 0) return false;
            if (strcasecmp($this->value, 'null') === 0) return false;
            if (strcasecmp($this->value, '-') === 0) return false;
        }
        return $this->value == true;
    }
    
    /* Returns contents as array or hashmap */
    public function asArray() {
        if ($this->type === self::TYPE_ARRAY) {
            
            $result = array();
            foreach ($this->value as $value) {
                $value = new CommandValue($this->ductape, $value, $this->defaultType);
                $result = array_merge($result, $value->asArray());
            }
            return $result;
            
        } elseif ($this->type === self::TYPE_SET) {
            
            return $this->ductape->getDataset($this->asDatasetId());
        } elseif ($this->type === self::TYPE_CHEQUER) {
            
            return $this->asChequer()->evaluate('array');            
            
        } elseif ($this->type === self::TYPE_FILE) {
            
            return $this->decodeFileContents()->asArray();

        } elseif ($this->type === self::TYPE_JSON) {
            
            $value = json_decode($this->value, JSON_OBJECT_AS_ARRAY);
            if (json_last_error()) throw new Exception("Json format is incorrect in: '{$this->value}'");
            return $value;
            
        } elseif ($this->type === self::TYPE_OBJECT) {
            
            return $this->value;
            
        } elseif (!$this->value) {
            return array();
        }
        return preg_split('/\r?\n/', $this->value, -1, PREG_SPLIT_NO_EMPTY);
    }
    
    
    public function storeArray($data) {
        if ($this->isEmpty()) return;
        
        if (is_scalar($data)) {
            $data = preg_split('/\r?\n/', $data, -1, PREG_SPLIT_NO_EMPTY);
        }
        
        if ($this->type === self::TYPE_SET) {
            $this->ductape->setDataset($data, $this->asDatasetId());
        } elseif ($this->asFilePath()) {
            $this->encodeFileContents($data);
        } else {
            throw new \Exception("Can't store data into " . $this->getShortDescription());
        }
        return $data;
    }

    
    public function storeString($data) {
        if ($this->isEmpty()) return;
        
        if (is_array($data)) {
            $data = implode("\n", $data);
        }
        
        if ($this->type === self::TYPE_SET) {
            $this->ductape->setDataset($data, $this->asDatasetId());
        } elseif ($this->asFilePath()) {
            $this->encodeFileContents($data);
        } else {
            throw new \Exception("Can't store data into " . $this->getShortDescription());
        }
        return $data;
    }

    
    public function storeContent($data) {
        if ($this->isEmpty()) return;
        
        if ($this->type === self::TYPE_SET) {
            $this->ductape->setDataset($data, $this->asDatasetId());
        } elseif ($this->asFilePath()) {
            $this->storeFileContents($data);
        } else {
            throw new \Exception("Can't store data into " . $this->getShortDescription());
        }
        return $data;
    }
    
    
    protected function parse($allowArray) {
        $this->type = $this->defaultType;

        if ($this->value === null) return;
        
        $value = $this->value;
        if (is_string($this->value)) $this->identifier = $this->value;
        
        if (is_array($value)) {
            $this->type = isset($value[0]) && $allowArray ? self::TYPE_ARRAY : self::TYPE_OBJECT;
            return;
        }
        
        if (!is_string($value) || strlen($value) < 3) {
            return;
        }
        
        $first = $value[0];
        $last = substr($value, -1);
        
        if ($first === CommandValue::CH_FILE && $last === CommandValue::CH_FILE && strpos($value, "\n") === false) {
            
            $this->type = self::TYPE_FILE;
            $this->identifier = substr($value, 1, -1);
            
        } elseif ($first === CommandValue::CH_SET && $last === CommandValue::CH_SET && strpos($value, "\n") === false) {
            
            $this->type = self::TYPE_SET;
            $this->identifier = substr($value, 1, -1);
            
        } elseif ($first === '$' && $value[1] === ' ') {
            
            $this->type = self::TYPE_CHEQUER;
            $this->identifier = substr($value, 1, -1);

        } elseif ($first === '\\' && $value[1] === '$') {
            
            // chequer escaping
            $this->type = self::TYPE_STRING;
            $this->value = substr($value, 1);
            
        } elseif (($first === '{' && $last === '}') || ($first === '[' && $last === ']')) {
            
            $this->type = self::TYPE_JSON;
            
        }
    }


    public function getShortDescription() {
        switch ($this->getType()) {
            case CommandValue::TYPE_ARRAY:
                return '[Array]';
            case CommandValue::TYPE_STRING:
                return '"' . substr($this->value, 0, 32) . '"';
            case CommandValue::TYPE_JSON:
                return '{JSON}';
            case CommandValue::TYPE_OBJECT:
                return '{Object}';
            default:
                return substr($this->getRaw(), 0, 32);
        }
    }
    
}