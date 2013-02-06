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
                ->setDescription("Combines multiple PHP scripts into one.")
                ->addOption('comments', null, InputOption::VALUE_OPTIONAL, 'Option to leave or strip comments from the source.', true)
                ->addOption('filter', null, InputOption::VALUE_OPTIONAL, 'Filter input files using Chequer Query Language.')
                ->addOption('includes-filter', null, InputOption::VALUE_OPTIONAL, 'Filter include\'s parsing using Chequer Query Language.')
                ->addOption('allow-missing-includes', null, InputOption::VALUE_OPTIONAL, 'Allow missing includes.', true)
                ->addOption('base-dir', null, InputOption::VALUE_OPTIONAL, 
                        "Base directory used when resolving paths of __DIR__ and __FILE__ constants.\n
                            It is required if the directory in which you will store the output will be different, 
                            or you are outputting to stdout.")
                ;
        
    }

    public function getInputSets() {
        return array(Ductape::SET_FILES => array());
    }
    
    public function getOutputSets() {
        return array('content' => array(
            'description' => 'Where to store the combined source.'
        ));
    }
    
    protected function execute( InputInterface $input, OutputInterface $output ) {

        $files = $this->readInputData($input, $output, 'files');
        
        $filesFilter = $this->getInputValue('filter', $input, CommandValue::TYPE_JSON)->getArray();
        $includesFilter = $this->getInputValue('includes-filter', $input, CommandValue::TYPE_JSON)->getArray();
        
        if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            if ($filesFilter !== null) $output->writeln("filtering files with " . json_encode($filesFilter));
            if ($includesFilter !== null) $output->writeln("filtering includes with " . json_encode($includesFilter));
        }
        
        if ($filesFilter !== null) $files = array_filter($files, new Chequer($filesFilter));

        $combiner = new \Ductape\Parser\SourceCombiner($files);
        $combiner->setComments($this->getInputValue('comments', $input)->getBool());
        $combiner->setAllowMissingIncludes($this->getInputValue('allow-missing-includes', $input)->getBool());
        $combiner->setBaseDir($this->getInputValue('base-dir', $input)->getString());
        if ($includesFilter !== null) $combiner->setIncludesFilter($includesFilter);

        $code = $combiner->combine();
        
        $this->writeOutputData($code, $input, $output, 'content', false);
        
    }


}