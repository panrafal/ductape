<?php

namespace A {
    // A::foo
    function foo() {
        
    }
    
}

namespace B {
    // test_file2.php should be included
    require_once str_replace('test_file1.php', 'test_file2.php', __FILE__);

    // B::foo
    function foo() {
        
    }
    
}