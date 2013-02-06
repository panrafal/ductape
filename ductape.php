#!/usr/bin/env php
<?php

// installed via composer?
if (file_exists($a = __DIR__.'/../../autoload.php')) {
    require_once $a;
} else {
    require_once __DIR__.'/vendor/autoload.php';
}

$application = new Ductape\Ductape();
$application->run();

