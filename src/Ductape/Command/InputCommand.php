<?php

namespace Ductape\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class InputCommand extends AbstractCommand {

    protected function configure() {
        parent::configure();
    }


    public function shouldOutputElements(InputInterface $input, OutputInterface $output) {
        return false;
    }

}