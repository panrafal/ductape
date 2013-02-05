<?php

namespace Ductape\Command\Php;

use Chequer;
use Ductape\Analyzer\ProcessAnalyzer;
use Ductape\Command\AbstractCommand;
use Ductape\Command\CommandValue;
use Ductape\Ductape;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CombinePhpCommand extends AbstractCommand {

    protected function configure() {
        parent::configure();

        $this->setName('combine-php')
                ->setDescription("Combines multiple PHP scripts into one.\n
                    Hmmmm
                    ")
                ->addArgument('combined', InputArgument::REQUIRED, 'Filepath to store the results.')
                ->addOption('comments', null, InputOption::VALUE_OPTIONAL, 'Option to leave or strip comments from the source.', false)
                ->addOption('filter', null, InputOption::VALUE_OPTIONAL, 'Filter input files using Chequer Query Language.')
                ;
        
    }

    public function getInputSets() {
        return array(Ductape::SET_FILES => array());
    }
    
    public function getOutputSets() {
        return array();
    }
    
    protected function execute( InputInterface $input, OutputInterface $output ) {

        $files = $this->readInputData($input, $output, 'files');
        
        $comments = $this->getInputValue('comments', $input)->getBool();
        
        $filesFilter = $this->getInputValue('filter', $input, CommandValue::TYPE_JSON)->getArray();
        
        if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            if ($filesFilter !== null) $output->writeln("filtering files with " . json_encode($filesFilter));
        }
        

        if ($filesFilter !== null) $files = array_filter($files, new Chequer($filesFilter));
        
        
    }


}