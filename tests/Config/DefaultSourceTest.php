<?php
namespace Scoutapm\Tests\Config;

use \Scoutapm\Agent;
use \Scoutapm\Config\DefaultSource;
use \PHPUnit\Framework\TestCase;

/**
 * Test Case for @see \Scoutapm\Config
 */
final class ConfigDefaultSourceTest extends TestCase {
  public function testHasKey() {
    $defaults = new DefaultSource();
    $this->assertTrue($defaults->has_key("api_version"));
    $this->assertFalse($defaults->has_key("notAValue"));
  }

  public function testGet()
  {
    $defaults = new DefaultSource();
    $this->assertEquals("1.0", $defaults->get("api_version"));
  }
}
