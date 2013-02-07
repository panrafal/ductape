<?php
/*
 * This file is part of DUCTAPE project.
 * 
 * Copyright (c)2013 Rafal Lindemann <rl@stamina.pl>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ductape\Command\Utility;

class CallCommand extends PipeCommand {

    protected function configure() {
        parent::configure();

        $this->setName('call')
                ->setDescription('Executes provided process.')
                ;
        
    }

    public function getInputSets() {
        return array();
    }
    
    public function getOutputSets() {
        return array();
    }



}