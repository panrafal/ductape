<?php

namespace Test\A {
 
    use TestInterface;
    
    class Bar implements TestInterface {
        use TestTrait;
        static $foo = "Test\\A\\Bar\\\$foo";
        public static function foo() {
            return "Test\\A\\Bar\\foo()";
        }
        
    }
    
}

namespace Test\B {

    use Test\A\Bar as ABar;
    
    class Bar extends ABar {
        const FOO = "Test\\B\\Bar\\FOO";
        
    }
    
}
