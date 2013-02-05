<?php

namespace Ductape\Command\InputOutput;

use Chequer;
use Ductape\Command\CommandValue;
use Ductape\Command\InputOutputCommand;
use Ductape\Console\Construction;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FilterCommand extends InputOutputCommand {

    protected function configure() {
        parent::configure();

        $this
                ->setName('filter')
                ->setAliases(array('io'))
                ->setDescription('Streams and optionally filters the data.')
                ->addArgument('filter', InputArgument::OPTIONAL, 'Data filter in Chequer Query Language.')
                ->addOption('clear', 'c', InputOption::VALUE_OPTIONAL, 'Clear the data.')
            ;
        
    }

    public function getInputSets() {
        return [Construction::SET_DATA => array()];
    }
    
    public function getOutputSets() {
        return [Construction::SET_DATA => array()];
    }
    
    protected function execute( InputInterface $input, OutputInterface $output ) {

        $data = $this->readInputData($input, $output);

        $filter = $this->getInputValue('filter', $input, CommandValue::TYPE_JSON);
        $filter = $filter->isEmpty() ? null : $filter->getArray();
        
        if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            if ($filter !== null) $output->writeln("Filtering with " . json_encode($filter));
        }
        
        if ($this->getInputValue('clear', $input)->getBool()) {
            $data = array();
        } elseif ($filter !== null) {
            $data = array_filter($data, new Chequer($filter));
        }
        
        $this->writeOutputData($data, $input, $output);
        
    }


}