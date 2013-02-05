<?php

namespace Ductape\Command\InputOutput;

use Ductape\Command\CommandInterface;
use Ductape\Command\CommandValue;
use Ductape\Command\InputOutputCommand;
use Ductape\Console\Construction;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends InputOutputCommand {

    protected function configure() {
        parent::configure();

        $this
                ->setName('run')
                ->setDescription('Runs ductape setup')
                ->addArgument('commands', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, '', array('ductape.json'))
//                ->addArgument('filter', InputArgument::REQUIRED, 'Filter in Chequer Query Language.')
            ;
        
    }

    public function getInputSets() {
        return array();
    }
    
    public function getOutputSets() {
        return array();
    }
    
    protected function execute( InputInterface $input, OutputInterface $output ) {

        $elements = $this->getApplication()->getElements(Construction::SET_ALL);

        $commands = $this->getInputValue('commands', $input, CommandValue::TYPE_FILE)->getArray();
        
        if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            $output->writeln(sprintf("Running %d commands", count($commands)));
        }

        $construction = new Construction();
        $construction->setAutoExit(false);
        $construction->setElements($elements, Construction::SET_ALL);
        
        foreach($commands as $commandName => $inputArray) {
            if (is_numeric($commandName)) {
                if (!isset($inputArray['command'])) {
                    $commandName = key($inputArray);
                    $inputArray = current($inputArray);
                } else {
                    $commandName = $inputArray['command'];
                    unset($inputArray['command']);
                }
            }
            if (!$commandName) throw new Exception("Command name is missing!");
            $command = null;
            try {
                $command = $construction->find(self::fromCamelCase($commandName));
            } catch (Exception $e) {
                $command = $construction->find($commandName);
            }
            
            
            if ($command instanceof CommandInterface) {
                $input = $command->createInputFromOptions($inputArray);
            } else {
                $input = self::guessInputFromOptions($command, $inputArray);
            }
            
            if (($result = $construction->run($input, $output))) {
                throw new \Exception("Last command '$commandName' has failed with result $result");
            }
            
        }
        
    }

    

    public static function fromCamelCase($id) {
        return preg_replace_callback('/([a-z])([A-Z])/', function($match) {
            return $match[1] . '-' . strtolower($match[2]);
        }, $id);
    }

    
    public static function toCamelCase($id) {
        return preg_replace_callback('/-(\w)/', function($match) {
            return strtoupper($match[1]);
        }, $id);
    }     

    
    public static function guessInputFromOptions(Command $command, $input) {
        $def = $command->getDefinition();
        $result = array(
            $command->getName()
        );
        foreach($input as $key => $value) {
            $dashedKey = self::fromCamelCase($key);
            if ($def->hasArgument($key)) {
                $result[$key] = $value;
            } elseif ($def->hasOption($key)) {
                $result['--'.$key] = $value;
            } elseif ($def->hasArgument($dashedKey)) {
                $result[$dashedKey] = $value;
            } elseif ($def->hasOption($dashedKey)) {
                $result['--'.$dashedKey] = $value;
            } else {
                throw new \Exception("Unknown option '$key'");
            }
        }
        return new ArrayInput($result, $command->getDefinition());
    }
    
}