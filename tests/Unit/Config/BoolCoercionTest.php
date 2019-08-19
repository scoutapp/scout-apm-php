<?php
namespace Scoutapm\UnitTests\Config;

use \PHPUnit\Framework\TestCase;
use \Scoutapm\Config\BoolCoercion;

/**
 * Test Case for @see \Scoutapm\Config
 */
final class BoolCoercionTest extends TestCase
{
    public function testParsesStrings()
    {
        $c = new BoolCoercion();

        $this->assertEquals(true, $c->coerce('t'));
        $this->assertEquals(true, $c->coerce('true'));
        $this->assertEquals(true, $c->coerce('1'));
        $this->assertEquals(true, $c->coerce('yes'));
        $this->assertEquals(true, $c->coerce('YES'));
        $this->assertEquals(true, $c->coerce('T'));
        $this->assertEquals(true, $c->coerce('TRUE'));

        // Falses
        $this->assertEquals(false, $c->coerce('f'));
        $this->assertEquals(false, $c->coerce('false'));
        $this->assertEquals(false, $c->coerce('no'));
        $this->assertEquals(false, $c->coerce('0'));
    }

    public function testIgnoresBooleans()
    {
        $c = new BoolCoercion();

        $this->assertEquals(true, $c->coerce(true));
        $this->assertEquals(false, $c->coerce(false));
    }

    public function testNullIsFalse()
    {
        $c = new BoolCoercion();

        $this->assertEquals(false, $c->coerce(null));
    }

    public function testAnythingElseIsFalse()
    {
        $c = new BoolCoercion();

        // $c is "any object"
        $this->assertEquals(false, $c->coerce($c));
    }
}
