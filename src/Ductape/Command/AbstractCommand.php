<?php

namespace Ductape\Command;

use Ductape\Console\Construction;
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
        
        $i = 0;
        foreach($inSets as $set => $info) {
            $this->addOption($set.'-in', $i == 0 ? 'i' : null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL
                    , 'Input '.$set.'. A list of values, $SET$, {JSON} or #FILE#.');
            ++$i;
        }
        
        $i = 0;
        foreach($outSets as $set => $info) {
            $this->addOption($set.'-out', $i == 0 ? 'o' : null, InputOption::VALUE_OPTIONAL
                    , 'Output '.$set.'. A $SET$ or #FILE#.');
            if (array_key_exists($set, $inSets)) {
                $this->shortcircuits[] = $set;
                $this->addOption($set, null, InputOption::VALUE_OPTIONAL
                        , "Shortcut for --{$set}-in=value --{$set}-out=value.");
            }
            ++$i;
        }
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
            $fileCompare = $fileCompare->getFilePath();
            foreach($sets as $set) {
                if ($this->getInputValue($set . '-' . $direction, $input)->getFilePath() == $fileCompare) {
                    // it is used already
                    return;
                }
            }
            var_dump($input->getOptions());
            if ($this->getInputValue($sets[0] . '-' . $direction, $input)->isEmpty()) {
                $this->setInputValue($sets[0] . '-' . $direction, $file, $input);
            }
        }
    }
    
    private function setupDefaultDataSetsRedirection(InputInterface $input) {
        $inSets = array_keys($this->getInputSets());
        $outSets = array_keys($this->getOutputSets());
        
        if ($inSets 
                && $inSets[0] == Construction::SET_DATA 
                && $this->getApplication()->lastDataSet != Construction::SET_DATA
                && $this->getInputValue($inSets[0] . '-in', $input)->isEmpty()
        ) {
            $this->setInputValue($inSets[0] . '-in', '$' . $this->getApplication()->lastDataSet . '$', $input);            
        }
        
        if ($outSets
                && $outSets[0] == Construction::SET_DATA 
                && $this->getApplication()->lastDataSet != Construction::SET_DATA
                && $this->getInputValue($outSets[0] . '-out', $input)->isEmpty()
        ) {
            $this->setInputValue($outSets[0] . '-out', '$' . $this->getApplication()->lastDataSet . '$', $input);
        }
        
        if ($outSets && $outSets[0] != Construction::SET_DATA) {
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
            //$this->setStreamRedirection($this->getInputSets(), 'in', '#stdin#', $input);
            $this->setStreamRedirection($this->getOutputSets(), 'out', '#stdout#', $input);
        }
        
        $this->setupDefaultDataSetsRedirection($input);
        
        if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            $output->writeln($this->getVerboseInfo($input, $output));
        }
        
    }

    
    /** @return Construction */
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
            throw new \Exception("Value {$name} is not defined!");
        }
        return new CommandValue($this, $value, $defaultType, $allowArray);
    }
    
    public function setInputValue($name, $value, InputInterface $input) {
        if ($value instanceof CommandValue) $value = $value->raw();
        if ($input->hasArgument($name)) {
            $input->setArgument($name, $value);
        } elseif ($input->hasOption($name)) {
            $value = $input->setOption($name, $value);
        } else {
            throw new \Exception("Value {$name} is not defined!");
        }
    }
    
    public function readInputData(InputInterface $input, OutputInterface $output, $set = Construction::SET_DATA) {
        $sets = array_keys($this->getInputSets());
        
        if (!$sets) throw new \Exception('No input sets defined!');
            
        if ($set === Construction::SET_DATA) $set = $sets[0];
        
        $value = $this->getInputValue($set . '-in', $input);
        if ($value->isEmpty()) {
            // default handling
            $data = $this->getApplication()->getDataSet($set);
            $readFrom = "\$$set\$";
        } else {
            $data = $value->getArray();
            $readFrom = $value->getShortDescription();
        }
        if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            $output->writeln(sprintf('read %d %s from %s', count($data), $set, $readFrom));
        }
        return $data;
    }
    
    public function writeOutputData($data, InputInterface $input, OutputInterface $output, $set = Construction::SET_DATA) {
        $sets = array_keys($this->getOutputSets());
        
        if (!$sets) throw new \Exception('No input sets defined!');

        if ($set === Construction::SET_DATA) $set = $sets[0];

        $value = $this->getInputValue($set . '-out', $input);
        if ($value->isEmpty()) {
            // default handling
            $this->getApplication()->setDataSet($data, $set);
            $wroteTo = "\$$set\$";
        } else {
            if ($value->isElementsSet()) {
                $this->getApplication()->setDataSet($data, $value->getSetId());
            } elseif ($value->getFilePath()) {
                if (!count($data) || isset($data[0])) {
                    $dataString = implode("\n", $data);
                } else {
                    $dataString = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                }
                if ($value->getFilePath() === 'php://stdout') {
                    $output->write($dataString, OutputInterface::OUTPUT_RAW);
                } else {
                    file_put_contents($value->getFilePath(), $dataString);
                }
            }
            $wroteTo = $value->getShortDescription();
        }
        if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            $output->writeln(sprintf('wrote %d %s to %s', count($data), $set, $wroteTo));
        }
        
    }

    /** @return Input */
    public function createInputFromOptions($options) {
        return InputOutput\RunCommand::guessInputFromOptions($this, $options);
    }

    
}