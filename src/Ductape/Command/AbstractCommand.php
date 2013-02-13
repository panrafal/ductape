<?php
/*
 * This file is part of DUCTAPE project.
 * 
 * Copyright (c)2013 Rafal Lindemann <rl@stamina.pl>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ductape\Command;

use Ductape\Ductape;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCommand extends Command implements CommandInterface {

    protected $shortcircuits = array();
    

    protected function configure() {
        parent::configure();
        
        $inSets = $this->getInputSets();
        $outSets = $this->getOutputSets();
        
        $options = array();
        
        $i = 0;
        foreach($inSets as $set => $info) {
            $options[$i.'-in'] = new InputOption($set.'-in', $i == 0 ? 'i' : null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL
                    , 'Input '.$set.'. A list of values, $SET$, {JSON} or #FILE#.');
            ++$i;
        }
        
        $i = 0;
        foreach($outSets as $set => $info) {
            $options[$i.'-out'] = new InputOption($set.'-out', $i == 0 ? 'o' : null, InputOption::VALUE_OPTIONAL
                    , 'Output '.$set.'. A $SET$ or #FILE#.');
            if (array_key_exists($set, $inSets)) {
                $this->shortcircuits[] = $set;
                $options[$i.'-'] = new InputOption($set, null, InputOption::VALUE_OPTIONAL
                        , "Shortcut for --{$set}-in=value --{$set}-out=value.");
            }
            ++$i;
        }
        ksort($options);
        $this->getDefinition()->addOptions($options);
    }

    
    protected function getVerboseInfo(InputInterface $input, OutputInterface $output) {
        $s = "\n------------------------------\n";
        $s .= "Running <comment>".$this->getName()."</comment> with options: ";
        $params = array_merge(
                    array_map('json_decode', array_diff_assoc( array_map('json_encode', $input->getArguments()), array_map('json_encode', $this->getDefinition()->getArgumentDefaults()) )), 
                    array_map('json_decode', array_diff_assoc( array_map('json_encode', $input->getOptions()), array_map('json_encode', $this->getDefinition()->getOptionDefaults()) ))
                );
        unset($params[0]);
        unset($params['command']);
        $s .= json_encode($params, /*JSON_PRETTY_PRINT |*/ JSON_UNESCAPED_SLASHES);
        $s .= "\n";
        return $s;
    }
    
    
    private function setStreamRedirection($sets, $direction, $file, InputInterface $input) {
        $sets = array_keys($sets);
        if ($sets) {
            $fileCompare = new CommandValue($this, $file);
            $fileCompare = $fileCompare->asFilePath();
            foreach($sets as $set) {
                if ($this->getInputValue($set . '-' . $direction, $input)->asFilePath() == $fileCompare) {
                    // it is used already
                    return;
                }
            }
            if ($this->getInputValue($sets[0] . '-' . $direction, $input)->isEmpty()) {
                $this->setInputValue($sets[0] . '-' . $direction, $file, $input);
            }
        }
    }
    
    
    private function setupDefaultDataSetsRedirection(InputInterface $input) {
        $inSets = array_keys($this->getInputSets());
        $outSets = array_keys($this->getOutputSets());
        
        if ($inSets 
                && $inSets[0] == Ductape::SET_DATA 
                && $this->getApplication()->lastDataSet != Ductape::SET_DATA
                && $this->getInputValue($inSets[0] . '-in', $input)->isEmpty()
        ) {
            $this->setInputValue($inSets[0] . '-in', CommandValue::CH_SET . $this->getApplication()->lastDataSet . CommandValue::CH_SET, $input);            
        }
        
        if ($outSets
                && $outSets[0] == Ductape::SET_DATA 
                && $this->getApplication()->lastDataSet != Ductape::SET_DATA
                && $this->getInputValue($outSets[0] . '-out', $input)->isEmpty()
        ) {
            $this->setInputValue($outSets[0] . '-out', CommandValue::CH_SET . $this->getApplication()->lastDataSet . CommandValue::CH_SET, $input);
        }
        
        if ($outSets && $outSets[0] != Ductape::SET_DATA) {
            $this->getApplication()->lastDataSet = $outSets[0];
        }
    }
    
    protected function initialize(InputInterface $input, OutputInterface $output) {

        // setup shortcircuits (--set -> --set-in = --set-out)
        foreach($this->shortcircuits as $set) {
            $value = $this->getInputValue($set, $input);
            if ($value->isEmpty() == false) {
                $this->setInputValue($set . '-in', $value->getRaw(), $input);
                $this->setInputValue($set . '-out', $value->getRaw(), $input);
            }
        }

        /* setup stdout. always-on stdin causes trouble on windows, so for now it will be off */
        if ($input instanceof ArgvInput) {
            $this->setStreamRedirection($this->getInputSets(), 'in', '#stdin#', $input);
            $this->setStreamRedirection($this->getOutputSets(), 'out', '#stdout#', $input);
        }
        
        $this->setupDefaultDataSetsRedirection($input);
        
        if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            $output->writeln($this->getVerboseInfo($input, $output));
        }
        
    }

    
    /** @return Ductape */
    public function getApplication() {
        return parent::getApplication();
    }

    
    /** @return CommandValue */
    public function getInputValue($name, InputInterface $input, $defaultType = CommandValue::TYPE_STRING) {
        $value = null;
        $allowArray = false;
        if ($input->hasArgument($name)) {
            $value = $input->getArgument($name);
            $allowArray = $this->getDefinition()->getArgument($name)->isArray();
        } elseif ($input->hasOption($name)) {
            $value = $input->getOption($name);
            $allowArray = $this->getDefinition()->getOption($name)->isArray();
        } else {
            throw new Exception("Value {$name} is not defined!");
        }
        return new CommandValue($this, $value, $defaultType, $allowArray, $input);
    }
    
    
    public function setInputValue($name, $value, InputInterface $input) {
        if ($value instanceof CommandValue) $value = $value->raw();
        if ($input->hasArgument($name)) {
            $input->setArgument($name, $value);
        } elseif ($input->hasOption($name)) {
            $value = $input->setOption($name, $value);
        } else {
            throw new Exception("Value {$name} is not defined!");
        }
    }

    /** Returns input value controlling the data input 
     * @return CommandValue
     */
    protected function getInputDataValue(InputInterface $input, $set = Ductape::SET_DATA, $setDefault = true) {
        $sets = array_keys($this->getInputSets());
        
        if (!$sets) throw new Exception('No input sets defined!');
            
        if ($set === Ductape::SET_DATA) $set = $sets[0];
        
        $value = $this->getInputValue($set . '-in', $input);
        
        if ($setDefault && $value->isEmpty()) $value = CommandValue::createSetId($this->getApplication(), $set);
        return $value;
    }
    
    /** Returns input value controlling the data output
     * @return CommandValue
     */
    protected function getOutputDataValue(InputInterface $input, $set = Ductape::SET_DATA, $setDefault = true) {
        $sets = array_keys($this->getOutputSets());
        
        if (!$sets) throw new Exception('No input sets defined!');

        if ($set === Ductape::SET_DATA) $set = $sets[0];

        $value = $this->getInputValue($set . '-out', $input);
        
        if ($setDefault && $value->isEmpty()) $value = CommandValue::createSetId($this->getApplication(), $set);
        return $value;
    }
    
    protected function readInputData(InputInterface $input, OutputInterface $output, $set = Ductape::SET_DATA, $mode = Ductape::MODE_ARRAY) {
        return $this->getApplication()->readData($this->getInputDataValue($input, $set), $output, $set, $mode);
    }
    
    protected function writeOutputData($data, InputInterface $input, OutputInterface $output, $set = Ductape::SET_DATA, $mode = Ductape::MODE_ARRAY) {
        $this->getApplication()->writeData($data, $this->getOutputDataValue($input, $set), $output, $set, $mode);
    }

    
    /** @return Input */
    public function createInputFromParams($options) {
        return Ductape::guessCommandInputFromParams($this, $options);
    }

    
}