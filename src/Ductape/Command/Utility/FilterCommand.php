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
                ->setHelp('Streams and optionally filters the data. Following options are applied in provided order.')
                ->addOption('clear', 'c', null, 'Clear the data.')
                ->addOption('filter', null, InputOption::VALUE_OPTIONAL, 'Data filter in Chequer Query Language.')
                ->addOption('walk', null, InputOption::VALUE_OPTIONAL, 'Applies CQL query to every item in the data. NULL values are skipped.')
                ->addOption('walkmerge', null, InputOption::VALUE_OPTIONAL, 'Applies CQL query to every item and merges the data.')
                ->addOption('diff', null, InputOption::VALUE_OPTIONAL, 'Substract from the set.')
                ->addOption('intersect', null, InputOption::VALUE_OPTIONAL, 'Intersect two sets.')
                ->addOption('merge', null, InputOption::VALUE_OPTIONAL, 'Merge two sets.')
                ->addOption('reverse', null, null/*InputOption::VALUE_OPTIONAL*/, 'Reverse the set.')
                ->addOption('sort', null, InputOption::VALUE_OPTIONAL, 'Sort items. 1 for ascending, -1 for descending, CQL for comparisons:
"$ .a > .b ? 1 : (.a = .b ? 0 : -1)"
')
                ->addOption('unique', null, InputOption::VALUE_OPTIONAL, 'Leave only unique items')
            ;
        
    }

    public function getInputSets() {
        return [Ductape::SET_DATA => array()];
    }
    
    public function getOutputSets() {
        return [Ductape::SET_DATA => array()];
    }
    
    protected function execute( InputInterface $input, OutputInterface $output ) {

        $verbose = $output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL;
        $data = $this->readInputData($input, $output);

        
        if ($this->getInputValue('clear', $input)->asBool()) {
            $data = array();
            if ($verbose) $output->writeln("data cleared");
        }
        
        if (($chequer = $this->getInputValue('filter', $input)->asChequer())) {
            $data = array_filter($data, $chequer);
            if ($verbose) $output->writeln(count($data) . " after filtering with " . $chequer);
        }
        
        if (($chequer = $this->getInputValue('walk', $input)->asChequer())) {
            $data = $chequer->walk($data);
            if ($verbose) $output->writeln(count($data) . " after walking with " . $chequer);
        }
        
        if (($chequer = $this->getInputValue('walkmerge', $input)->asChequer())) {
            $data = $chequer->merge($data);
            if ($verbose) $output->writeln(count($data) . " after walking&merging with " . $chequer);
        }
        if ($this->getInputValue('reverse', $input)->asBool()) {
            $data = array_reverse($data);
            if ($verbose) $output->writeln('data reversed');
        }
        
        if (($operand = $this->getInputValue('diff', $input)) && !$operand->isEmpty()) {
            $data = array_diff($data, $operand->asArray());
            if ($verbose) $output->writeln(count($data) . " after substracting " . $operand->getShortDescription());
        }
        
        if (($operand = $this->getInputValue('intersect', $input)) && !$operand->isEmpty()) {
            $data = array_intersect($data, $operand->asArray());
            if ($verbose) $output->writeln(count($data) . " after intersecting " . $operand->getShortDescription());
        }
        
        if (($operand = $this->getInputValue('merge', $input)) && !$operand->isEmpty()) {
            if ($operand->isArray()) {
                $data = array_merge($data, $operand->asArray());
            } elseif ($operand->asBool()) {
                array_push($data, $operand->asString());
            } else {
                $data = array_merge($data);
            }
            if ($verbose) $output->writeln(count($data) . " after merging " . $operand->getShortDescription());
        }
        
        if (($operand = $this->getInputValue('sort', $input)) && !$operand->isEmpty()) {
            if ($operand->isChequer()) {
                $chequer = $operand->asChequer();
                usort($data, function($a, $b) {
                    return $chequer->check(array('a' => $a, 'b' => $b));
                });
            } else {
                sort($data, $operand->asString() > 0 ? SORT_ASC : SORT_DESC);
            }
            if ($verbose) $output->writeln("sorted with " . $operand->getShortDescription());
        }   
        
        if ($this->getInputValue('unique', $input)->asBool()) {
            $data = array_unique($data);
            if ($verbose) $output->writeln(count($data) . " after unique");
        }        
        
        
        $this->writeOutputData($data, $input, $output);
        
    }


}