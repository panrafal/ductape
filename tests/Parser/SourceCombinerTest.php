<?php

require_once __DIR__ . '/../bootstrap.php';

class SourceCombinerTest extends PHPUnit_Framework_TestCase {

	protected function setUp() {
    }

    protected function tearDown() {
    }

    public function testIncludes() {
        $combiner = new Ductape\Parser\SourceCombiner(array(
            __DIR__ . '/test_outer.php'
        ));
        // check basedir
        $combiner->setBaseDir(dirname(__DIR__) . '/Command');
        $code = $combiner->combine();
        
        file_put_contents(__DIR__ . '/test_result.php', $code);
        
        $this->assertEquals(self::stripWhitespace(
                    "<?php
                    namespace {
                    }
                    /* include_once test_file1.php */
                    namespace A {
                        // A::foo
                        function foo()
                        {
                        }
                    }
                    namespace B {
                        // test_file2.php should be included
                    }
                    /* include_once test_file2.php */
                    namespace {
                        // ::foo
                        function foo()
                        {
                        }
                    }
                    namespace B {//;
                        // B::foo
                        function foo()
                        {
                        }
                    }
                    namespace {//;
                        // should not be included again
                        //;
                        // should be left intact
                        require __DIR__ . '/../Parser' /*__DIR__*/ . '/../Command/data.txt';
                        // should be left intact
                        @include_once 'test_missing.php';
                        function someFunc()
                        {
                            // should not be included again
                            //;
                            // should be left intact
                            @require_once dirname(__DIR__ . '/../Parser/test_outer.php' /*__FILE__*/) . '/test_missing.php';
                        }
                    }"
                ), self::stripWhitespace($code));
        
    }
    
    protected static function stripWhitespace($code) {
        $code = preg_replace('/^\s*(\r?\n)?/m', '', $code);
        $code = str_replace("\r", "", $code);
        return trim($code);
    }
    
}

