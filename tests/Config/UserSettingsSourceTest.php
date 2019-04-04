<?php
namespace Scoutapm\Tests\Config;

use \Scoutapm\Config\UserSettingsSource;
use \PHPUnit\Framework\TestCase;

final class ConfigUserSettingsSourceTest extends TestCase {
  public function testHasKeyAfterBeingSet() {
    $config = new UserSettingsSource();
    $this->assertFalse($config->has_key("foo"));

    $config->set("foo", "bar");

    $this->assertTrue($config->has_key("foo"));
  }

  public function testGet()
  {
    $config = new UserSettingsSource();
    $this->assertNull($config->get("foo"));

    $config->set("foo", "bar");

    $this->assertEquals("bar", $config->get("foo"));
  }
}
