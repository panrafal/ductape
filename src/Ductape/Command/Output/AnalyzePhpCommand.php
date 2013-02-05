<?php

namespace Ductape\Command\Output;

use Ductape\Command\OutputCommand;
use Ductape\Console\Construction;
use Ductape\ProcessAnalyzer;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AnalyzePhpCommand extends OutputCommand {

    protected function configure() {
        parent::configure();

        $this
                ->setName('analyze-php')
                ->setAliases(array('analyze'))
                ->setDescription('Executes provided script and analyzes it\'s dependencies.')
                ->addArgument('script', InputArgument::REQUIRED, 'Script to run')
                ->addOption('globals', null, InputOption::VALUE_OPTIONAL, 'JSON encoded globals hashmap. {"_SERVER" : {"REQUEST_URI":"/"}}', false)
                ->addOption('http', null, InputOption::VALUE_OPTIONAL, 'Full URL to set in the environment, faking a HTTP REQUEST', false)
                ->addArgument('filter', InputArgument::REQUIRED, 'Filter files in Chequer Query Language.')

                ;
        
    }

    public function getInputSets() {
        return [
            Construction::SET_FILES => array(), 
            'classes' => array()];
    }
    
    public function getOutputSets() {
        return [
            Construction::SET_FILES => array(), 
            'classes' => array(), 
            'classmap' => array()
            ];
    }
    
    protected function execute( InputInterface $input, OutputInterface $output ) {

        $files = $this->getInputElements($input, 'files');
        $classes = $this->getInputElements($input, 'classes');
        
        $pa = new \Ductape\Analyzer\ProcessAnalyzer();
        
        $env = array();
        
        if ($input->getOption('http')) $env = $pa->fakeHttpGlobals($input->getOption('http'));
        
        $globals = $input->getOption('globals');
        if ($globals) $env = array_merge($env, json_decode($globals));

        $filesFilter = $this->getInputValue('filter', $input)->getArray();
//        $classFilter = $this->getInputValue('class-filter', $input)->getArray();
        
        if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            if ($filesFilter) $output->writeln("Filtering files with " . json_encode($filesFilter));
//            if ($classFilter) $output->writeln("Filtering classes with " . json_encode($classFilter));
        }
        

        
        $result = $pa->analyzeFile($input->getArgument('script'), $env, $classes);
        
        if (!$result) {
            $output->writeln("Script failed!");
            exit(1);
        }

        if ($filesFilter) $result['files'] = array_filter($result['files'], new \Chequer($filesFilter));
        
        $files = array_unique(array_merge($files, $result['files']));
        $classes = array_unique(array_merge($files, array_keys($result['classes'])));
        $classmap = array_merge($files, $result['classes']);


        $this->storeOutputElements($files, $input, $output, 'files');
        $this->storeOutputElements($classes, $input, $output, 'classes');
        $this->storeOutputElements($classmap, $input, $output, 'classmap');
        
    }


}