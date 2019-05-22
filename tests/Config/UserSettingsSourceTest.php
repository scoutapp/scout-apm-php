<?php
namespace Scoutapm\Tests\Config;

use \PHPUnit\Framework\TestCase;
use \Scoutapm\Config\UserSettingsSource;

final class ConfigUserSettingsSourceTest extends TestCase
{
    public function testHasKeyAfterBeingSet()
    {
        $config = new UserSettingsSource();
        $this->assertFalse($config->hasKey("foo"));

        $config->set("foo", "bar");

        $this->assertTrue($config->hasKey("foo"));
    }

    public function testGet()
    {
        $config = new UserSettingsSource();
        $this->assertNull($config->get("foo"));

        $config->set("foo", "bar");

        $this->assertEquals("bar", $config->get("foo"));
    }
}
