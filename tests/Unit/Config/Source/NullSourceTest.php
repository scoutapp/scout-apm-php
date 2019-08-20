<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Config\Source;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config\Source\NullSource;

/** @covers \Scoutapm\Config\Source\NullSource */
final class NullSourceTest extends TestCase
{
    public function testHasKey() : void
    {
        $defaults = new NullSource();
        self::assertTrue($defaults->hasKey('apiVersion'));
        self::assertTrue($defaults->hasKey('notAValue'));
    }

    public function testGet() : void
    {
        $defaults = new \Scoutapm\Config\Source\NullSource();
        self::assertEquals(null, $defaults->get('apiVersion'));
        self::assertEquals(null, $defaults->get('weirdThing'));
    }
}
