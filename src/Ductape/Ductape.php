<?php

namespace Ductape;

use Ductape\Command\CommandInterface;
use Ductape\Command\CommandValue;
use Ductape\Command\Php\AnalyzePhpCommand;
use Ductape\Command\Php\CombinePhpCommand;
use Ductape\Command\Php\PharCommand;
use Ductape\Command\Utility\CallCommand;
use Ductape\Command\Utility\FilesCommand;
use Ductape\Command\Utility\FilterCommand;
use Ductape\Command\Utility\PipeCommand;
use Ductape\Command\Utility\RunCommand;
use Exception;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Rafal Lindemann
 */
class Ductape extends Application {

    protected $data = array();
    
    const SET_ALL = 'all';
    const SET_DATA = 'data';
    const SET_FILES = 'files';
    
    public $lastDataSet = self::SET_DATA;

    
    public function __construct() {
        parent::__construct('DUCTAPE', '0.1');

        $this->add(new AnalyzePhpCommand());
        $this->add(new CombinePhpCommand());
        $this->add(new FilesCommand());
        $this->add(new FilterCommand());
        $this->add(new RunCommand());
        $this->add(new CallCommand());
        $this->add(new PipeCommand());
        $this->add(new PharCommand());
        
        $this->setAutoExit(false);
    }


    public function getLongVersion() {
        return parent::getLongVersion() . ' by <comment>Rafal Lindemann</comment>';
    }

    
    public function doRun( InputInterface $input, OutputInterface $output ) {
        return parent::doRun($input, $output);
    }


    /**
     * Run multiple commands from the array.
     * 
     * Commands can be defined in three ways. You can mix the syntax if You like:
     * 
     * PHP - like. Single command can only be used once!
     * ```
     * [
     *      'command1' => [params],
     *      'command2' => [params],
     * ]
     * ```
     * 
     * Every command is in it's own array,
     * ```
     * [
     *      ['command1' => [params]]
     *      ['command2' => [params]]
     * ]
     * ```
     * 
     * Every command is in it's own hashmap with obligatory 'command' key,
     * ```
     * [
     *      [
     *          'command' => 'command1',
     *          'param1' => ...,
     *      ]
     *      [
     *          'command' => 'command2',
     *          'param1' => ...,
     *      ]
     * ]
     * ```
     * 
     * You can do comments by prepending '@' character to key names like this:
     * 
     * ```
     * [
     *      '@commented-out' => 'comment'
     * ]
     * ```
     * 
     */
    public function runCommands($commands, OutputInterface $output = null) {
        if (!$output) $output = new NullOutput();
        
        foreach($commands as $commandName => $params) {
            if (is_numeric($commandName)) {
                if (!isset($params['command'])) {
                    $this->runCommands($params, $output);
                    continue;
                } else {
                    $commandName = $params['command'];
                    unset($params['command']);
                }
            }
            if (!$commandName) throw new Exception("Command name is missing!");
            if ($commandName[0] == '@') continue;

            if (($result = $this->runCommand($commandName, $params, $output))) {
                throw new Exception("Last command '$commandName' has failed with result $result");
            }        
            
        }
        
    }
   
    
    /**
     * Run single command with parameters.
     * 
     * @param $commandName Name of the command, or Command object
     * @param $params Parameters to pass to the command
     */
    public function runCommand($commandName, $params, OutputInterface $output = null) {
        if (!$output) $output = new NullOutput();

        $command = null;
        /* @var $command Command */
        
        if ($commandName instanceof Command) {
            $command = $commandName;
            $commandName = $command->getName();
        }
        
        if (!$command) {
            try {
                $command = $this->find(self::fromCamelCase($commandName));
            } catch (Exception $e) {
                $command = $this->find($commandName);
            }
        }

        if ($command instanceof CommandInterface) {
            $input = $command->createInputFromParams($params);
        } else {
            $input = self::guessCommandInputFromOptions($command, $params);
        }

        return $this->run($input, $output);
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

    
    public static function guessCommandInputFromParams(Command $command, $input) {
        $def = $command->getDefinition();
        $result = array(
            'command' => $command->getName()
        );
        foreach($input as $key => $value) {
            if ($key[0] === '@') continue;
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
        return new ArrayInput($result);
    }    


    /** Returns specified dataset, or all datasets if SET_ALL is used */
    public function getDataset($set = self::SET_DATA) {
        if ($set === self::SET_ALL) return $this->data;
        if (isset($this->data[$set])) return $this->data[$set];
        return array();
    }

    
    /** Sets specified dataset, or all datasets if SET_ALL is used */
    public function setDataset($elements, $set = self::SET_DATA) {
        if ($set === self::SET_ALL) 
            $this->data = $elements;
        else 
            $this->data[$set] = $elements;
    }
    

    /** 
     * Reads data from data source. 
     * 
     * @param $value Source specifier. ( "$set$", "#file#", "string", [data], ... )
     * @param $output Output for verbose information
     * @param $defaultSet Default dataset to use, when value is empty
     * @param $asArray Treat data as array or string
     * 
     * @return array|string
     */
    public function readData($value, OutputInterface $output, $defaultSet = null, $asArray = false) {
        $value = CommandValue::ensure($this, $value);
        if ($value->isEmpty()) {
            // default handling
            if (!$defaultSet) return null;
            
            $data = $this->getDataset($defaultSet);
            $readFrom = "\$$defaultSet\$";
        } else {
            $data = $asArray ? $value->getArray() : $value->getString();
            $readFrom = $value->getShortDescription();
        }
        if ($output && $output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            $output->writeln(sprintf('read %d %s from %s', is_array($data) ? count($data) : strlen($data), $defaultSet, $readFrom));
        }
        return $data;
    }    
    
    
    /** 
     * Writes data to datasource 
     * 
     * @param $value Destination specifier. ( "$set$", "#file#", "file" )
     * @param $asArray When TRUE and the data is a string, it will be split into an array.
     * 
     */
    public function writeData($data, $value, OutputInterface $output, $defaultSet = null, $asArray = false) {
        $value = CommandValue::ensure($this, $value);
        
        if ($asArray && is_string($data)) {
            $data = preg_split('/\r?\n/', $data, -1, PREG_SPLIT_NO_EMPTY);
        }
        
        if ($value->isEmpty()) {
            // default handling
            if (!$defaultSet) return;
            $this->setDataset($data, $defaultSet);
            $wroteTo = "\$$defaultSet\$";
        } else {
            if ($value->isElementsSet()) {
                $this->setDataset($data, $value->getSetId());
            } elseif ($value->getFilePath()) {
                if (is_string($data)) {
                    $dataString = $data;
                } else {
                    if (!count($data) || isset($data[0])) {
                        // probably an array. store line-by-line
                        $dataString = implode("\n", $data);
                    } else {
                        // everything else store as JSON
                        $dataString = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    }
                }
                if ($value->getFilePath() === 'php://stdout') {
                    $output->write($dataString, OutputInterface::OUTPUT_RAW);
                } else {
                    file_put_contents($value->getFilePath(), $dataString);
                }
            }
            $wroteTo = $value->getShortDescription();
        }
        if ($output && $output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            $output->writeln(sprintf('wrote %d %s to %s', is_array($data) ? count($data) : strlen($data), $defaultSet, $wroteTo));
        }
    }    
    
}