<?php
declare(strict_types=1);

namespace Scoutapm\UnitTests\Config;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config\DefaultSource;

/**
 * @covers \Scoutapm\Config\DefaultSource
 */
final class DefaultSourceTest extends TestCase
{
    public function testHasKey()
    {
        $defaults = new DefaultSource();
        self::assertTrue($defaults->hasKey("api_version"));
        self::assertFalse($defaults->hasKey("notAValue"));
    }

    public function testGet()
    {
        $defaults = new DefaultSource();
        self::assertEquals("1.0", $defaults->get("api_version"));
    }
}
