<?php

namespace Ductape\Console;

use Ductape\Command\InputOutput\RunCommand;
use Ductape\Command\Output\AnalyzePhpCommand;
use Ductape\Command\Output\FilesCommand;
use Ductape\Command\Output\FilterCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Rafal Lindemann
 */
class Construction extends Application {

    protected $data = array();
    
    const SET_ALL = 'all';
    const SET_DEFAULT = 'default';
    const SET_FILES = 'files';
    
    
    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct('PHP Builder');

        $this->add(new AnalyzePhpCommand());
        $this->add(new FilesCommand());
        $this->add(new FilterCommand());
        $this->add(new RunCommand());
        
    }


    public function getLongVersion() {
        return parent::getLongVersion() . ' by <comment>Rafal Lindemann</comment>';
    }

    public function doRun( InputInterface $input, OutputInterface $output ) {
        return parent::doRun($input, $output);
    }


    public function getDataSet($set = self::SET_DEFAULT) {
        if ($set === self::SET_ALL) return $this->data;
        if (isset($this->data[$set])) return $this->data[$set];
        return array();
    }

    public function setDataSet($elements, $set = self::SET_DEFAULT) {
        if ($set === self::SET_ALL) 
            $this->data = $elements;
        else 
            $this->data[$set] = $elements;
    }
    
   
}