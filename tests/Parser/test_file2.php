<?php
namespace Test;
use Test\B as C;

// ::foo
function foo() {
    return "\\Test\\foo()";
}

$bar = new C\Bar();

return 
    \foo()
    . ', ' . \A\foo() 
    . ', ' . \B\foo()
    . ', ' . C\Bar::FOO
    . ', ' . A\Bar::foo()
    . ', ' . A\Bar::$foo
    . ', ' . $bar->bar()
    . ', ' . get_class($bar);
