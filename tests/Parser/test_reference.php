<?php
namespace A {
    // A::foo
    function foo()
    {
        return 'A\\foo()';
    }
}
namespace B {
    // B::foo
    function foo()
    {
        // test_file2.php should be included, but not dependent
        //;
        return 'B\\foo()';
    }
}
namespace {
    interface TestInterface
    {
        
    }
}
namespace {
    use Test\B;
    //;
    // should not be included again
    //;
    // should be left intact
    require __DIR__ . '/../Parser' /*__DIR__*/ . '/../Command/data.txt';
    // should be left intact
    @include_once 'test_missing.php';
    function foo()
    {
        // should be ignored
        new B\Bar();
        // should not be included again
        //;
        // should be left intact
        @include_once dirname(__DIR__ . '/../Parser/test_outer.php' /*__FILE__*/) . '/test_missing.php';
        return '\\foo()';
    }
}
namespace Test\A {
    trait TestTrait
    {
        public function bar()
        {
            return 'A\\TestTrait\\bar()';
        }
    }
}
namespace Test\A {
    use TestInterface;
    class Bar implements TestInterface
    {
        use TestTrait;
        static $foo = 'Test\\A\\Bar\\$foo';
        public static function foo()
        {
            return 'Test\\A\\Bar\\foo()';
        }
    }
}
namespace Test\B {
    use Test\A\Bar as ABar;
    class Bar extends ABar
    {
        const FOO = 'Test\\B\\Bar\\FOO';
    }
}
namespace Test {
    use Test\B as C;
    // ::foo
    function foo()
    {
        return '\\Test\\foo()';
    }
    $bar = new C\Bar();
    return \foo() . ', ' . \A\foo() . ', ' . \B\foo() . ', ' . C\Bar::FOO . ', ' . A\Bar::foo() . ', ' . A\Bar::$foo . ', ' . $bar->bar() . ', ' . get_class($bar);
}
