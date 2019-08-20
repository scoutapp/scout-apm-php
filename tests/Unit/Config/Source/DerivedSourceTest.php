<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Config\Source;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config;
use Scoutapm\Config\Source\DerivedSource;

/** @covers \Scoutapm\Config\Source\DerivedSource */
final class DerivedSourceTest extends TestCase
{
    public function testHasKey() : void
    {
        $derived = new DerivedSource(new Config());

        self::assertTrue($derived->hasKey('testing'));
        self::assertFalse($derived->hasKey('is_array'));
    }

    public function testGet() : void
    {
        $derived = new DerivedSource(new Config());

        self::assertSame('derived api version: 1.0', $derived->get('testing'));
    }
}
