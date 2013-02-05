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
            if ($this->getInputValue($sets[0] . '-' . $direction, $input)->isEmpty()) {
                $this->setInputValue($sets[0] . '-' . $direction, $file, $input);
            }
        }
    }
    
    protected function getVerboseInfo(InputInterface $input, OutputInterface $output) {
        $s = "\nRunning <comment>".$this->getName()."</comment> with options:\n";
        
        $s .= json_encode(array_merge(
                    @array_diff_assoc( $input->getArguments(), $this->getDefinition()->getArgumentDefaults() ), 
                    @array_diff_assoc( $input->getOptions(), $this->getDefinition()->getOptionDefaults() )
                ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $s .= "\n------------------------------\n";
        return $s;
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

        if ($input instanceof ArgvInput) {
            $this->setStreamRedirection($this->getInputSets(), 'in', '#stdin#', $input);
            $this->setStreamRedirection($this->getOutputSets(), 'out', '#stdout#', $input);
        }
        
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
        if ($input->hasArgument($name)) {
            $value = $input->getArgument($name);
        } elseif ($input->hasOption($name)) {
            $value = $input->getOption($name);
        } else {
            throw new \Exception("Value {$name} is not defined!");
        }
        return new CommandValue($this, $value, $defaultType);
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
    
    public function getInputElements(InputInterface $input, $set = Construction::SET_DEFAULT) {
        $sets = array_keys($this->getInputSets());
        
        if (!$sets) throw new \Exception('No input sets defined!');
            
        if ($set === Construction::SET_DEFAULT) $set = $sets[0];
        
        $value = $this->getInputValue($set . '-in', $input);
        if ($value->isEmpty()) {
            // default handling
            return $this->getApplication()->getElements($set);
        } else {
            return $value->getArray();
        }
        
    }
    
    public function storeOutputElements($elements, InputInterface $input, OutputInterface $output, $set = Construction::SET_DEFAULT) {
        $sets = array_keys($this->getOutputSets());
        
        if (!$sets) throw new \Exception('No input sets defined!');

        if ($set === Construction::SET_DEFAULT) $set = $sets[0];

        $value = $this->getInputValue($set . '-out', $input);
        if ($value->isEmpty()) {
            // default handling
            $this->getApplication()->setElements($elements, $set);
        } else {
            if ($value->isElementsSet()) {
                $this->getApplication()->setElements($elements, $value->getSetId());
            } elseif ($value->getFilePath()) {
                if (!count($elements) || isset($elements[0])) {
                    $elements = implode("\n", $elements);
                } else {
                    $elements = json_encode($elements, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                }
                if ($value->getFilePath() === 'php://stdout') {
                    $output->write($elements, OutputInterface::OUTPUT_RAW);
                } else {
                    file_put_contents($value->getFilePath(), $elements);
                }
            }
        }
        
    }

    /** @return Input */
    public function createInputFromOptions($options) {
        return InputOutput\RunCommand::guessInputFromOptions($this, $options);
    }

    
}