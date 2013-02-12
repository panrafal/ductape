<?php
/*
 * This file is part of DUCTAPE project.
 * 
 * Copyright (c)2013 Rafal Lindemann <rl@stamina.pl>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ductape\Command\Utility;

use Chequer;
use Ductape\Command\AbstractCommand;
use Ductape\Command\CommandValue;
use Ductape\Ductape;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FilterCommand extends AbstractCommand {

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
        return [Ductape::SET_DATA => array()];
    }
    
    public function getOutputSets() {
        return [Ductape::SET_DATA => array()];
    }
    
    protected function execute( InputInterface $input, OutputInterface $output ) {

        $data = $this->readInputData($input, $output);

        $filter = $this->getInputValue('filter', $input, CommandValue::TYPE_JSON);
        $filter = $filter->isEmpty() ? null : $filter->asArray();
        
        if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            if ($filter !== null) $output->writeln("Filtering with " . json_encode($filter));
        }
        
        if ($this->getInputValue('clear', $input)->asBool()) {
            $data = array();
        } elseif ($filter !== null) {
            $data = array_filter($data, new Chequer($filter));
        }
        
        $this->writeOutputData($data, $input, $output);
        
    }


}