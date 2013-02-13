<?php

namespace Ductape\Chequer;

use Ductape\Command\CommandValue;
use Ductape\Ductape;
use DynamicObject;

class DuctapeWrapper extends DynamicObject {

    protected $__command;
    protected $__input;
    
    public function __construct( Ductape $ductape = null, $command = null, $input = null ) {
        parent::__construct($ductape);
        
        $this->__command = $command;
        $this->__input = $input;
        if ($command && $input) {
            /* @var $command \Ductape\Command\AbstractCommand */
            $this->input = function($name) use($command, $input) {
                return $command->getInputValue($name, $input);
            };
        }
    }

    
    public function __invoke($value, $defaultType = CommandValue::TYPE_STRING, $allowArray = false) {
        return new CommandValue($this->__parent, $value, $defaultType, $allowArray, $this->__input);
    }


}