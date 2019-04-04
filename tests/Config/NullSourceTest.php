<?php
namespace Scoutapm\Tests\Config;

use \Scoutapm\Config\NullSource;
use \PHPUnit\Framework\TestCase;

final class ConfigNullSourceTest extends TestCase {
  public function testHasKey() {
    $defaults = new NullSource();
    $this->assertTrue($defaults->has_key("apiVersion"));
    $this->assertTrue($defaults->has_key("notAValue"));
  }

  public function testGet()
  {
    $defaults = new NullSource();
    $this->assertEquals(null, $defaults->get("apiVersion"));
    $this->assertEquals(null, $defaults->get("weirdThing"));
  }
}
