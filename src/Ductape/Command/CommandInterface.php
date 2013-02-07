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

use Symfony\Component\Console\Input\Input;

interface CommandInterface {
    
    function getInputSets();
    
    function getOutputSets();

    /** @return Input */
    function createInputFromParams($options);
    
}