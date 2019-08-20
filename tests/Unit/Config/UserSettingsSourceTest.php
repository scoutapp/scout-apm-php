<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Config;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config\UserSettingsSource;

final class UserSettingsSourceTest extends TestCase
{
    public function testHasKeyAfterBeingSet() : void
    {
        $config = new UserSettingsSource();
        $this->assertFalse($config->hasKey('foo'));

        $config->set('foo', 'bar');

        $this->assertTrue($config->hasKey('foo'));
    }

    public function testGet() : void
    {
        $config = new UserSettingsSource();
        $this->assertNull($config->get('foo'));

        $config->set('foo', 'bar');

        $this->assertEquals('bar', $config->get('foo'));
    }
}
