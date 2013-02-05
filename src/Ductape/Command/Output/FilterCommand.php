<?php

namespace Ductape\Command\Output;

use Ductape\Command\OutputCommand;
use Ductape\Console\Construction;
use Ductape\ProcessAnalyzer;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FilterCommand extends OutputCommand {

    protected function configure() {
        parent::configure();

        $this
                ->setName('filter')
                ->setDescription('Filters input using Chequer Query Language.')
                ->addArgument('filter', InputArgument::REQUIRED, 'Filter in Chequer Query Language.')
            ;
        
    }

    public function getInputSets() {
        return [Construction::SET_DEFAULT => array()];
    }
    
    public function getOutputSets() {
        return [Construction::SET_DEFAULT => array()];
    }
    
    protected function execute( InputInterface $input, OutputInterface $output ) {

        $elements = $this->readInputData($input);

        $filter = $this->getInputValue('filter', $input)->getArray();
        
        if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            if ($filter) $output->writeln("Filtering with " . json_encode($filter));
        }
        
        if ($filter) $elements = array_filter($elements, new \Chequer($filter));
        
        $this->writeOutputData($elements, $input, $output);
        
    }


}