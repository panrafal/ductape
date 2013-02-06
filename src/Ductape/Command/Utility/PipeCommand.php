<?php

namespace Ductape\Command\Utility;

use Ductape\Command\AbstractCommand;
use Ductape\Ductape;
use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class PipeCommand extends AbstractCommand {

    protected function configure() {
        parent::configure();

        $this->setName('call')
                ->setDescription('Executes provided shell command and pipes the data in and out.')
                ->addArgument('command', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Command to run')
                ->addOption('globals', null, InputOption::VALUE_OPTIONAL, 'JSON encoded globals hashmap. {"_SERVER" : {"REQUEST_URI":"/"}}', false)
                ->addOption('fake-http', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 
                        "Sets server variables to fake http requests.\nPass URLs or {url : globals} hashmaps.", array())
                ->addOption('filter', null, InputOption::VALUE_OPTIONAL, 'Filter files in Chequer Query Language.')
                
                ;
        
    }

    public function getInputSets() {
        return array(Ductape::SET_DATA => array());
    }
    
    public function getOutputSets() {
        return array(Ductape::SET_DATA => array());
    }
    
    protected function execute( InputInterface $input, OutputInterface $output ) {

        $stdin = null;
        
        if ($this->getInputSets()) {
            $data = $this->readInputData($input, $output);
            if ($data) {
                $stdin = is_array($data) ? implode("\n", $data) : $data;
            }
        }
        
        $command = $this->getInputValue('command', $input)->getArray();
        $command = implode(" ", array_map('escapeshellarg', $command));
        
        $process = new Process($command, null, null, $stdin);
        $result = $process->run(function($type, $text) {
            if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
                $output->write($text, OutputInterface::OUTPUT_RAW);
            }
        });
        
        if ($result) throw new Exception("Command returned an error: $result");

        if ($this->getOutputSets()) {
            $this->writeOutputData($process->getOutput(), $input, $output, Ductape::SET_DATA, true);
        }
        
        return $result;
        
        
    }


}