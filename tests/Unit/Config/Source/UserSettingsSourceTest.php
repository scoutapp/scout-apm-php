<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Config\Source;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config\Source\UserSettingsSource;

/** @covers \Scoutapm\Config\Source\UserSettingsSource */
final class UserSettingsSourceTest extends TestCase
{
    public function testHasKeyAfterBeingSet(): void
    {
        $config = new UserSettingsSource();
        self::assertFalse($config->hasKey('foo'));

        $config->set('foo', 'bar');

        self::assertTrue($config->hasKey('foo'));
    }

    public function testGet(): void
    {
        $config = new UserSettingsSource();
        self::assertNull($config->get('foo'));

        $config->set('foo', 'bar');

        self::assertSame('bar', $config->get('foo'));
    }
}
