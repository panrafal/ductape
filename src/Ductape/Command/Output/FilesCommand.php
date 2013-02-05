<?php

namespace Ductape\Command\Output;

use Ductape\Command\OutputCommand;
use Ductape\Console\Construction;
use Ductape\ProcessAnalyzer;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FilesCommand extends OutputCommand {

    protected function configure() {
        parent::configure();

        $this
                ->setName('files')
                ->setDescription('Finds files.')
                ->addArgument('paths', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Directory to look into, specific file, or glob pattern')
                ->addOption('filter', null, InputOption::VALUE_OPTIONAL, 'Filter in Chequer Query Language.', false)
                ->addOption('include-dirs', null, InputOption::VALUE_OPTIONAL, 'Include directories in the output.', false)
//                ->addOption('glob', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, '', false)
//                ->addOption('http', null, InputOption::VALUE_OPTIONAL, 'Full URL to set in the environment, faking a HTTP REQUEST', false)
//                ->addOption('include-class', 'c', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Additional class to include', array())
            ;
        
    }

    public function getInputSets() {
        return [Construction::SET_FILES => array()];
    }
    
    public function getOutputSets() {
        return [Construction::SET_FILES => array()];
    }
    
    protected function execute( InputInterface $input, OutputInterface $output ) {

        $files = $this->readInputData($input, $output, 'files');

        $filter = $this->getInputValue('filter', $input)->getArray();
        
        if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            if ($filter) $output->writeln("Filtering with " . json_encode($filter));
        }
        
        if ($filter) $filter = new \Chequer($filter);
        
        $includeDirs = $this->getInputValue('include-dirs', $input)->getBool();
        $globs = $this->getInputValue('paths', $input)->getArray();
        
        foreach($globs as $glob) {
            $iterator = is_dir($glob) 
                    ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($glob, \FilesystemIterator::SKIP_DOTS)) 
                    : new \GlobIterator($glob, \FilesystemIterator::SKIP_DOTS);
            foreach($iterator as $path => $file) {
                /* @var $file \SplFileInfo */
                if (!$includeDirs && $file->isDir()) continue;
                if (!$filter || $filter($file)) $files[] = $path;
            }
        }
        
        $this->writeOutputData(array_unique($files), $input, $output, 'files');
        
    }


}