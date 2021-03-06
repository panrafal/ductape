<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// composer autoloader
if (file_exists($loader = __DIR__.'/../../autoload.php')) {
    require_once $loader;
}

if (file_exists($loader = __DIR__.'/../vendor/autoload.php')) {
    require_once $loader;
}

