<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Config\Source;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config\Source\DefaultSource;

/** @covers \Scoutapm\Config\Source\DefaultSource */
final class DefaultSourceTest extends TestCase
{
    public function testHasKey(): void
    {
        $defaults = new DefaultSource();
        self::assertTrue($defaults->hasKey('api_version'));
        self::assertFalse($defaults->hasKey('notAValue'));
    }

    public function testGet(): void
    {
        $defaults = new DefaultSource();
        self::assertSame('1.0', $defaults->get('api_version'));
    }
}
