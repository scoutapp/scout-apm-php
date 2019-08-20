<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Config;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config\BoolCoercion;

/**
 * Test Case for @see \Scoutapm\Config
 */
final class BoolCoercionTest extends TestCase
{
    public function testParsesStrings() : void
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

    public function testIgnoresBooleans() : void
    {
        $c = new BoolCoercion();

        $this->assertEquals(true, $c->coerce(true));
        $this->assertEquals(false, $c->coerce(false));
    }

    public function testNullIsFalse() : void
    {
        $c = new BoolCoercion();

        $this->assertEquals(false, $c->coerce(null));
    }

    public function testAnythingElseIsFalse() : void
    {
        $c = new BoolCoercion();

        // $c is "any object"
        $this->assertEquals(false, $c->coerce($c));
    }
}
