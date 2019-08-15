<?php
namespace Scoutapm\Tests\Config;

use \PHPUnit\Framework\TestCase;
use \Scoutapm\Config\JSONCoercion;

/**
 * Test Case for @see \Scoutapm\Config
 */
final class JSONCoercionTest extends TestCase
{
    public function testParsesJSON()
    {
        $c = new JSONCoercion();
        $this->assertEquals([
            "foo" => 1
        ], $c->coerce('{"foo": 1}'));
    }

    // Return null for any invalid JSON
    public function testInvalidJSON()
    {
        $c = new JSONCoercion();
        $this->assertEquals(null, $c->coerce('foo: 1}'));
    }

    public function testIgnoresNonString()
    {
        $c = new JSONCoercion();
        $this->assertEquals(10, $c->coerce(10));
    }
}
