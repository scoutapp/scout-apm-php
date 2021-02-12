<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Config\Source;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config\Source\NullSource;

/** @covers \Scoutapm\Config\Source\NullSource */
final class NullSourceTest extends TestCase
{
    public function testHasKey(): void
    {
        $defaults = new NullSource();
        self::assertTrue($defaults->hasKey('apiVersion'));
        self::assertTrue($defaults->hasKey('notAValue'));
    }

    public function testGet(): void
    {
        $defaults = new NullSource();
        self::assertNull($defaults->get('apiVersion'));
        self::assertNull($defaults->get('weirdThing'));
    }
}
