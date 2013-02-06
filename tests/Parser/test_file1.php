<?php

namespace A {
    // A::foo
    function foo() {
        return "A\\foo()";
    }
    
}

namespace B {

    // B::foo
    function foo() {
        // test_file2.php should be included, but not dependent
        require_once str_replace('test_file1.php', 'test_file2.php', __FILE__);
        return "B\\foo()";
    }
    
}

namespace {
    interface TestInterface {

    }
}
