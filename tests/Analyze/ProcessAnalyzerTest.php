<?php

require_once __DIR__ . '/../bootstrap.php';

class ProcessAnalyzerTest extends PHPUnit_Framework_TestCase {

    /** @var Ductape\ProcessAnalyzer */
    protected $pa;

	protected function setUp() {
        $this->pa = new \Ductape\Analyzer\ProcessAnalyzer();
    }


    protected function tearDown() {
    }

//    public function testAnalyzeCode() {
//        $result = $this->pa->analyzeCode('<?php 
//            class TestClass {
//            }
//            echo "hello!";
//            ?'.'>');
//        
//        $this->assertNotNull($result);
//    }
    

    public function testAnalyzeFile() {
        $result = $this->pa->analyzeFile(__DIR__ . '/../builder.php');
        
        $this->assertNotNull($result);
    }

    public function testFakeHttpGlobals() {
        $globals = $this->pa->fakeHttpGlobals('https://www.foo.com/foo/bar?hello=world&array[]=one&array[]=two');
        
        $this->assertEquals('443', $globals['_SERVER']['SERVER_PORT']);
        $this->assertNotEmpty($globals['_SERVER']['HTTPS']);
        $this->assertEquals('www.foo.com', $globals['_SERVER']['HTTP_HOST']);
        $this->assertEquals('https://www.foo.com/foo/bar', $globals['_SERVER']['SCRIPT_URI']);
        $this->assertEquals('/foo/bar', $globals['_SERVER']['SCRIPT_URL']);
        $this->assertEquals('127.0.0.1', $globals['_SERVER']['REMOTE_ADDR']);
        $this->assertEquals('Aqueduct', $globals['_SERVER']['HTTP_USER_AGENT']);
        $this->assertEquals('GET', $globals['_SERVER']['REQUEST_METHOD']);
        $this->assertEquals('/foo/bar?hello=world&array[]=one&array[]=two', $globals['_SERVER']['REQUEST_URI']);
        $this->assertEquals('hello=world&array[]=one&array[]=two', $globals['_SERVER']['QUERY_STRING']);
        $this->assertEquals('world', $globals['_GET']['hello']);
        $this->assertEquals(array('one', 'two'), $globals['_GET']['array']);

        $globals = $this->pa->fakeHttpGlobals('/foo/bar?hello=world');
        $this->assertFalse($globals['_SERVER']['HTTPS']);
        $this->assertEquals('localhost', $globals['_SERVER']['HTTP_HOST']);
        $this->assertEquals('http://localhost/foo/bar', $globals['_SERVER']['SCRIPT_URI']);
        $this->assertEquals('/foo/bar?hello=world', $globals['_SERVER']['REQUEST_URI']);
        
        
    }
    
}

