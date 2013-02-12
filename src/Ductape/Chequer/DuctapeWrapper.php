<?php

namespace Ductape\Chequer;

use Ductape\Command\CommandValue;
use Ductape\Ductape;
use DynamicObject;

class DuctapeWrapper extends DynamicObject {

    public function __construct( Ductape $ductape = null ) {
        parent::__construct($ductape);
    }


    public function __invoke($value, $defaultType = CommandValue::TYPE_STRING, $allowArray = false) {
        return new CommandValue($this->__parent, $value, $defaultType, $allowArray);
    }


}