<?php
/*
 * This file is part of DUCTAPE project.
 * 
 * Copyright (c)2013 Rafal Lindemann <rl@stamina.pl>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ductape\Command\Php;

use Chequer;
use Ductape\Command\AbstractCommand;
use Ductape\Command\CommandValue;
use Ductape\Ductape;
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
        return array(
            'content' => array(
                'description' => 'Where to store the combined source.'
            ),
            'classmap' => array(
                'description' => 'Where to store the combined source.'
            ),
            'classes' => array(
                'description' => 'Where to store the combined source.'
            ),
            'files' => array(
                'description' => 'Where to store the combined source.'
            ),
            'files-info' => array(
                'description' => 'Where to store the combined source.'
            ),
            'classes-unknown' => array(
                'description' => 'Where to store the combined source.'
            ),
        );
    }
    
    protected function execute( InputInterface $input, OutputInterface $output ) {

        $files = $this->readInputData($input, $output, 'files');
        
        $filesFilter = $this->getInputValue('filter', $input)->asChequer();
        $includesFilter = $this->getInputValue('includes-filter', $input)->asChequer();
        
        if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            if ($filesFilter) $output->writeln("filtering files with " . $filesFilter);
            if ($includesFilter) $output->writeln("filtering includes with " . $includesFilter);
        }
        
        if ($filesFilter) $files = array_filter($files, $filesFilter);

        $combiner = new \Ductape\Parser\SourceCombiner($files);
        $combiner->setComments($this->getInputValue('comments', $input)->asBool());
        $combiner->setAllowMissingIncludes($this->getInputValue('allow-missing-includes', $input)->asBool());
        $combiner->setBaseDir($this->getInputValue('base-dir', $input)->asString());
        if ($includesFilter) $combiner->setIncludesFilter($includesFilter);

        $code = $combiner->combine();
        
        $this->writeOutputData($code, $input, $output, 'content', Ductape::MODE_CONTENT);
        
        if ($this->getOutputDataValue($input, 'classmap', false)->isEmpty() == false) {
            $this->writeOutputData($combiner->getClassmap(), $input, $output, 'classmap', Ductape::MODE_ARRAY);
        }
        if ($this->getOutputDataValue($input, 'classes', false)->isEmpty() == false) {
            $this->writeOutputData(array_keys($combiner->getClassmap()), $input, $output, 'classes', Ductape::MODE_ARRAY);
        }
        if ($this->getOutputDataValue($input, 'files', false)->isEmpty() == false) {
            $this->writeOutputData(array_keys($combiner->getParsedFilesInfo()), $input, $output, 'files', Ductape::MODE_ARRAY);
        }
        if ($this->getOutputDataValue($input, 'files-info', false)->isEmpty() == false) {
            $this->writeOutputData($combiner->getParsedFilesInfo(), $input, $output, 'files-info', Ductape::MODE_ARRAY);
        }
        if ($this->getOutputDataValue($input, 'classes-unknown', false)->isEmpty() == false) {
            $this->writeOutputData(array_keys($combiner->getUnknownClasses()), $input, $output, 'classes-unknown', Ductape::MODE_ARRAY);
        }
        
    }


}