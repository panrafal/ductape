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

class AnalyzePhpCommand extends AbstractCommand {

    protected function configure() {
        parent::configure();

        $this->setName('analyze-php')
                ->setDescription('Executes provided script and analyzes it\'s dependencies.')
                ->addArgument('script', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Script to run')
                ->addOption('globals', null, InputOption::VALUE_OPTIONAL, 'JSON encoded globals hashmap. {"_SERVER" : {"REQUEST_URI":"/"}}', false)
                ->addOption('fake-http', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 
                        "Sets server variables to fake http requests.\nPass URLs or {url : globals} hashmaps.", array())
                ->addOption('filter', null, InputOption::VALUE_OPTIONAL, 'Filter files in Chequer Query Language.')

                ;
        
    }

    public function getInputSets() {
        return [
            Ductape::SET_FILES => array(), 
            'classes' => array()];
    }
    
    public function getOutputSets() {
        return [
            Ductape::SET_FILES => array(), 
            'classes' => array(), 
            'classmap' => array()
            ];
    }
    
    protected function execute( InputInterface $input, OutputInterface $output ) {

        $files = $this->readInputData($input, $output, 'files');
        $classesIn = $this->readInputData($input, $output, 'classes');
        $classesOut = array();
        $classmap = array();
        
        $pa = new ProcessAnalyzer();
        
        $globals = $this->getInputValue('globals', $input)->getArray();
        
        $fakeHttp = $this->getInputValue('fake-http', $input)->getArray();
        
        $globals = $input->getOption('globals');
        if ($globals) $env = array_merge($env, json_decode($globals));

        $filesFilter = $this->getInputValue('filter', $input, CommandValue::TYPE_JSON)->getArray();
//        $classFilter = $this->getInputValue('class-filter', $input)->getArray();
        
        if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            if ($filesFilter !== null) $output->writeln("filtering files with " . json_encode($filesFilter));
//            if ($classFilter) $output->writeln("filtering classes with " . json_encode($classFilter));
        }
        
        if (!$fakeHttp) $fakeHttp = array(true);
        if (!is_array($globals)) $globals = array();
        
        $scripts = $this->getInputValue('script', $input)->getArray();

        
        foreach($scripts as $script) {
            foreach($fakeHttp as $url => $urlGlobals) {
                $env = array();
                if ($url !== 0 || $urlGlobals !== true) {
                    if (is_numeric($url)) {
                        $url = $urlGlobals;
                        $urlGlobals = array();
                    }
                    $env = array_merge($pa->fakeHttpGlobals($url), $globals, $urlGlobals);
                }
                if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
                    $output->writeln(sprintf("\nrunning script <comment>%s</comment> with globals %s", $script, json_encode($env, JSON_UNESCAPED_SLASHES)));
                }

                $result = $pa->analyzeFile($script, $env, $classesIn);

                if (!$result['success']) {
                    $output->writeln("Script output: \n" . $result['output']);
                    $output->writeln("\nScript failed!");
                    exit(1);
                }

                if ($filesFilter !== null) $result['files'] = array_filter($result['files'], new Chequer($filesFilter));

                $files = array_unique(array_merge($files, $result['files']));
                $classesOut = array_unique(array_merge($classesOut, array_keys($result['classes'])));
                $classmap = array_merge($classmap, $result['classes']);
            }
        }        

        $this->writeOutputData($files, $input, $output, 'files');
        $this->writeOutputData($classesOut, $input, $output, 'classes');
        $this->writeOutputData($classmap, $input, $output, 'classmap');
        
    }


}