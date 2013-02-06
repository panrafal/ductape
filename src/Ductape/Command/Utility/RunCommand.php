<?php

namespace Ductape\Command\Utility;

use Ductape\Command\AbstractCommand;
use Ductape\Command\CommandInterface;
use Ductape\Command\CommandValue;
use Ductape\Ductape;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends AbstractCommand {

    protected function configure() {
        parent::configure();

        $this
                ->setName('run')
                ->setDescription('Runs ductape setup')
                ->addArgument('commands', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, '', array('ductape.json'))
//                ->addArgument('filter', InputArgument::REQUIRED, 'Filter in Chequer Query Language.')
            ;
        
    }

    public function getInputSets() {
        return array(Ductape::SET_DATA => array());
    }
    
    public function getOutputSets() {
        return array();
    }
    
    protected function execute( InputInterface $input, OutputInterface $output ) {

        $elements = $this->getApplication()->getDataset(Ductape::SET_ALL);

        $commands = $this->getInputValue('commands', $input, CommandValue::TYPE_FILE)->getArray();
        
        if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            $output->writeln(sprintf("Running %d commands", count($commands)));
        }

        $construction = new Ductape();
        $construction->setAutoExit(false);
        // copy all sets
        $construction->setDataset($elements, Ductape::SET_ALL);
        // read specified data
        $construction->setDataset($this->readInputData($input, $output), Ductape::SET_DATA);
        
        $construction->runCommands($commands, $output);
        
    }

    


    
}