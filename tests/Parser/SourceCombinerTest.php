<?php

require_once __DIR__ . '/../bootstrap.php';

class SourceCombinerTest extends PHPUnit_Framework_TestCase {

	protected function setUp() {
    }

    protected function tearDown() {
    }

    public function testCombine() {
        $combiner = new Ductape\Parser\SourceCombiner(array(
            __DIR__ . '/test_outer.php',
            __DIR__ . '/test_class.php',
            __DIR__ . '/test_trait.php'
        ));
        // check basedir
        $combiner->setBaseDir(dirname(__DIR__) . '/Command');
        $code = $combiner->combine();
        
        file_put_contents(__DIR__ . '/test_result.php', $code);
        
        $this->assertEquals(self::stripWhitespace(file_get_contents(__DIR__ . '/test_reference.php')), self::stripWhitespace($code));
        
        $result = include __DIR__ . '/test_result.php';
        
        $this->assertEquals('\foo(), A\foo(), B\foo(), Test\B\Bar\FOO, Test\A\Bar\foo(), Test\A\Bar\$foo, A\TestTrait\bar(), Test\B\Bar', $result);
        
        echo $result;
        
    }
    
    protected static function stripWhitespace($code) {
        $code = preg_replace('/^\s*(\r?\n)?/m', '', $code);
        $code = str_replace("\r", "", $code);
        return trim($code);
    }
    
}

