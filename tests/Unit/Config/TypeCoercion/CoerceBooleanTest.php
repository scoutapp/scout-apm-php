<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Config\TypeCoercion;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config\TypeCoercion\CoerceBoolean;

/** @covers \Scoutapm\Config\TypeCoercion\CoerceBoolean */
final class CoerceBooleanTest extends TestCase
{
    public function testParsesStrings(): void
    {
        $c = new CoerceBoolean();

        // @todo use a data provider
        self::assertTrue($c->coerce('t'));
        self::assertTrue($c->coerce('true'));
        self::assertTrue($c->coerce('1'));
        self::assertTrue($c->coerce('yes'));
        self::assertTrue($c->coerce('YES'));
        self::assertTrue($c->coerce('T'));
        self::assertTrue($c->coerce('TRUE'));

        // Falses
        self::assertFalse($c->coerce('f'));
        self::assertFalse($c->coerce('false'));
        self::assertFalse($c->coerce('no'));
        self::assertFalse($c->coerce('0'));
    }

    public function testIgnoresBooleans(): void
    {
        $c = new CoerceBoolean();

        self::assertTrue($c->coerce(true));
        self::assertFalse($c->coerce(false));
    }

    public function testNullIsFalse(): void
    {
        $c = new CoerceBoolean();

        self::assertFalse($c->coerce(null));
    }

    public function testAnythingElseIsFalse(): void
    {
        $c = new CoerceBoolean();

        // $c is "any object"
        self::assertFalse($c->coerce($c));
    }
}
