<?php

namespace Ductape\Command;

use Symfony\Component\Console\Input\Input;

interface CommandInterface {
    
    function getInputSets();
    
    function getOutputSets();

    /** @return Input */
    function createInputFromParams($options);
    
}