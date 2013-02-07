<?php

namespace Ductape\Command\Utility;

use Ductape\Command\AbstractCommand;
use Ductape\Command\CommandValue;
use Ductape\Ductape;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends AbstractCommand {

    protected function configure() {
        parent::configure();

        $this
                ->setName('run')
                ->setDescription('Runs ductape setup')
                ->addArgument('commands', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, '', array('ductape.json'))
                ->addOption('return-data', 'return', InputOption::VALUE_OPTIONAL, 'Which dataset to return. Defaults to last dataset used.')
//                ->addArgument('filter', InputArgument::REQUIRED, 'Filter in Chequer Query Language.')
            ;
        
    }

    public function getInputSets() {
        return array(Ductape::SET_DATA => array());
    }
    
    public function getOutputSets() {
        return array(Ductape::SET_DATA => array());
    }
    
    protected function execute( InputInterface $input, OutputInterface $output ) {

        $data = $this->getApplication()->getDataset(Ductape::SET_ALL);

        $commands = $this->getInputValue('commands', $input, CommandValue::TYPE_FILE)->getArray();
        
        if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            $output->writeln(sprintf("Running %d commands:", count($commands)));
        }

        $construction = new Ductape();
        $construction->setAutoExit(false);
        // copy all sets
        $construction->setDataset($data, Ductape::SET_ALL);
        // read specified data
        $construction->setDataset($this->readInputData($input, $output), Ductape::SET_DATA);

        $construction->runCommands($commands, $output);

        $data = null;
        $returnData = $this->getInputValue('return-data', $input);
        if ($returnData->isEmpty()) {
            $data = $construction->getDataset($construction->lastDataSet);
        } elseif ($returnData->getBool()) {
            $data = $construction->getDataset($returnData->getString());
        }
        
        if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            $output->writeln(sprintf("Finished running...", count($commands)));
        }        
        
        if ($data !== null) {
            $this->writeOutputData($data, $input, $output);
        }
    }

    


    
}