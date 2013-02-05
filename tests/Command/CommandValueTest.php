<?php

use Ductape\Command\CommandValue;
use Ductape\Console\Construction;

require_once __DIR__ . '/../bootstrap.php';

class CommandValueTest extends PHPUnit_Framework_TestCase {

    /** @var Construction */
    protected $construction;
    protected $command;

	protected function setUp() {
        $this->construction = new Construction();
        $this->command = $this->construction->find('analyze-php');
        $this->construction->setDataSet(array('element1', 'element2'));
    }
    
    public function testEmpty() {
        $value = new CommandValue($this->command, null);
        $this->assertTrue($value->isEmpty());
        $this->assertFalse($value->isArray());
        $this->assertEquals(null, $value->getRaw());
        $this->assertEquals(null, $value->getString());
        
        $value = new CommandValue($this->command, array(), CommandValue::TYPE_STRING, true);
        $this->assertTrue($value->isEmpty());
        $this->assertFalse($value->isArray());
        $this->assertEquals(null, $value->getRaw());
        $this->assertEquals(null, $value->getString());

        $value = new CommandValue($this->command, array(), CommandValue::TYPE_STRING, false);
        $this->assertFalse($value->isEmpty());

        $value = new CommandValue($this->command, true);
        $this->assertFalse($value->isEmpty());
    }

    public function testAllowArray() {

        $value = new CommandValue($this->command, array('foo'), CommandValue::TYPE_STRING, true);
        $this->assertEquals(CommandValue::TYPE_STRING, $value->getType());
        $this->assertEquals('foo', $value->getRaw());
        
        $value = new CommandValue($this->command, array('foo'), CommandValue::TYPE_STRING, false);
        $this->assertEquals(CommandValue::TYPE_OBJECT, $value->getType());
        $this->assertEquals(array('foo'), $value->getRaw());
        
    }
    
    public function testGetString() {
        $value = new CommandValue($this->command, 'foo');
        $this->assertEquals('foo', $value->getString());
        
        $value = new CommandValue($this->command, ['foo', 'bar'], CommandValue::TYPE_STRING, true);
        $this->assertEquals('foobar', $value->getString());
        
        $value = new CommandValue($this->command, ['foo', 'bar'], CommandValue::TYPE_STRING, false);
        $this->assertEquals("foo\nbar", $value->getString());
        
        $value = new CommandValue($this->command, ['["foo", "bar"]']);
        $this->assertEquals('["foo", "bar"]', $value->getString(), 'Should return JSON intact');
    }
    
    public function testGetArray() {
        $value = new CommandValue($this->command, 'foo');
        $this->assertEquals(CommandValue::TYPE_STRING, $value->getType());
        $this->assertEquals(array('foo'), $value->getArray());
        
        $value = new CommandValue($this->command, ['foo', 'bar'], CommandValue::TYPE_STRING, true);
        $this->assertEquals(CommandValue::TYPE_ARRAY, $value->getType());
        $this->assertEquals(array('foo', 'bar'), $value->getArray());
        
        $value = new CommandValue($this->command, ['foo', 'bar'], CommandValue::TYPE_STRING, false);
        $this->assertEquals(CommandValue::TYPE_OBJECT, $value->getType());
        $this->assertEquals(array('foo', 'bar'), $value->getArray());
        
        $value = new CommandValue($this->command, '["foo", "bar"]');
        $this->assertEquals(CommandValue::TYPE_JSON, $value->getType());
        $this->assertEquals(array("foo", "bar"), $value->getArray());
        
        $value = new CommandValue($this->command, ['["foo", "bar"]', "baz"], CommandValue::TYPE_STRING, true);
        $this->assertEquals(array("foo", "bar", "baz"), $value->getArray());
        
        $value = new CommandValue($this->command, ['["foo", "bar"]', "baz"], CommandValue::TYPE_STRING, false);
        $this->assertEquals(array('["foo", "bar"]', "baz"), $value->getArray());
    }

    public function testGetSet() {
        $value = new CommandValue($this->command, 'foo');
        $this->assertEquals(null, $value->getSetId());
        
        $value = new CommandValue($this->command, '$data$');
        $this->assertEquals('data', $value->getSetId());
        $this->assertEquals($this->construction->getDataSet(), $value->getArray());
        
        $this->assertEquals("element1\nelement2\n", $value->getString());
        
    }    

    public function testGetFile() {
        $value = new CommandValue($this->command, 'foo');
        $this->assertFalse($value->isFile());
        $this->assertEquals('foo', $value->getFilePath());

        $value = new CommandValue($this->command, 'stdin');
        $this->assertFalse($value->isFile());
        $this->assertEquals('php://stdin', $value->getFilePath());

        $value = new CommandValue($this->command, '#data.txt#');
        $this->assertEquals(file_get_contents('data.txt'), $value->getString());
        $this->assertEquals(preg_split('/\r?\n/', file_get_contents('data.txt'), -1, PREG_SPLIT_NO_EMPTY), $value->getArray());

        $value = new CommandValue($this->command, '#data.json#');
        $this->assertEquals(file_get_contents('data.json'), $value->getString());
        $this->assertEquals(array("foo" => "bar", "bar" => "baz"), $value->getArray());
        
        $value = new CommandValue($this->command, ['test', '#data.txt#', '#data.json#'], CommandValue::TYPE_STRING, true);
        $this->assertEquals('test' . file_get_contents('data.txt') . file_get_contents('data.json'), $value->getString());
        $this->assertEquals(array("test", "line1", "line2", "#data.json#", "foo" => "bar", "bar" => "baz"), $value->getArray());
        
    }    
    
}