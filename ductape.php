<?php

// installed via composer?
if (file_exists($a = __DIR__.'/../../autoload.php')) {
    require_once $a;
} else {
    require_once __DIR__.'/vendor/autoload.php';
}

// run only if called directly
if (preg_match('/ductape[^\/]*\.(php|phar)$/', $_SERVER['SCRIPT_NAME'])) {
    $application = new Ductape\Ductape();
    $application->run();
}

