<?php

require_once __DIR__ . '/test_file1.php';

// should not be included again
require_once dirname(__FILE__) . '/' . 'test_file1.php';

// should be left intact
require __DIR__ . '/../Command/data.txt';

// should be left intact
@include_once 'test_missing.php';

function someFunc() {
    // should not be included again
    require_once dirname(__FILE__) . '/test_file2.php';
    // should be left intact
    @require_once dirname(__FILE__) . '/test_missing.php';
}
