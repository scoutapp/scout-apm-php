<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Config\TypeCoercion;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config\TypeCoercion\CoerceJson;

/** @covers \Scoutapm\Config\TypeCoercion\CoerceJson */
final class CoerceJsonTest extends TestCase
{
    public function testParsesJSON(): void
    {
        $c = new CoerceJson();
        self::assertEquals(
            ['foo' => 1],
            $c->coerce('{"foo": 1}')
        );
    }

    /**
     * Return null for any invalid JSON
     */
    public function testInvalidJSON(): void
    {
        $c = new CoerceJson();
        // @todo add a data provider for more invalid json strings
        self::assertNull($c->coerce('foo: 1}'));
    }

    public function testIgnoresNonString(): void
    {
        $c = new CoerceJson();
        self::assertSame(10, $c->coerce(10));
    }
}
