<?php

namespace Ductape\Command\Php;

use Chequer;
use Ductape\Command\AbstractCommand;
use Ductape\Command\CommandValue;
use Ductape\Ductape;
use Exception;
use Phar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PharCommand extends AbstractCommand {

    protected function configure() {
        parent::configure();

        $this->setName('phar')
                ->setDescription("Builds PHAR package.")
                ->addArgument('phar', InputArgument::REQUIRED, 'Phar file')
                ->addOption('stub', null, InputOption::VALUE_OPTIONAL, 
                        'Whole phar stub to use.')
                ->addOption('stub-file', 'sf', InputOption::VALUE_OPTIONAL, 
                        'File to bootstrap the phar.')
                ->addOption('base-dir', null, InputOption::VALUE_OPTIONAL, 
                        "Base directory used when resolving paths.")
                ->addOption('filter', null, InputOption::VALUE_OPTIONAL, 'Filter files using Chequer Query Language.')
                ;
        
    }

    public function getInputSets() {
        return array(Ductape::SET_FILES => array());
    }
    
    public function getOutputSets() {
        return array();
    }
    
    protected function execute( InputInterface $input, OutputInterface $output ) {

        ini_set('phar.readonly', 0);
        
        $files = $this->readInputData($input, $output, 'files');
        
        $filesFilter = $this->getInputValue('filter', $input, CommandValue::TYPE_JSON)->getArray();
        $pharFile = $this->getInputValue('phar', $input)->getString();
        
        $stub = $this->getInputValue('stub', $input)->getString();
        $stubFile = $this->getInputValue('stub-file', $input)->getString();
        
        if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            if ($filesFilter !== null) $output->writeln("filtering files with " . json_encode($filesFilter));
        }
        
        if ($filesFilter !== null) $files = array_filter($files, new Chequer($filesFilter));

        $files = array_merge($files, (array)$stubFile);
        
        $pharName = basename($pharFile);
        
        unlink($pharFile);
        $phar = new Phar($pharFile);
        
        $phar->startBuffering();
        
        $baseDir = $this->getInputValue('base-dir', $input)->getString();
        if (!$baseDir) $baseDir = dirname($pharFile);
        if (is_dir($baseDir)) {
            $baseDir = realpath($baseDir);
        } else {
            // show the warning...
            $output->writeln("Basedir <info>$baseDir</info> is not accessible. It may result in a broken phar!");
        }
        
        $filesIncluded = array();
        foreach($files as $file) {
            if (!$file) continue;
            
            if (!is_file($file)) throw new Exception("File '$file' is missing!");
            
            $file = realpath($file);
            
            if (isset($filesIncluded[$file])) continue;
            
            $path = \Ductape\Utility\FileHelper::relativePath($baseDir, $file, '/');
            $path = strtr($path, '\\', '/');
            
            $filesIncluded[$file] = $path;
            
            $phar->addFile($file, $path);
        }
        
        if ($stub === null && $stubFile) {
            $stubFile = $filesIncluded[realpath($stubFile)];
            $stub = $phar->createDefaultStub($stubFile, $stubFile);
        }
        
        if ($stub) $phar->setStub($stub);
        
        $phar->stopBuffering();

        
    }


}