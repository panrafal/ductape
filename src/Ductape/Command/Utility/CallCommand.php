<?php

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