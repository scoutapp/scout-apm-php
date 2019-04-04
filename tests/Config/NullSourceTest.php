<?php
namespace Scoutapm\Tests\Config;

use \Scoutapm\Config\NullSource;
use \PHPUnit\Framework\TestCase;

final class ConfigNullSourceTest extends TestCase {
  public function testHasKey() {
    $defaults = new NullSource();
    $this->assertTrue($defaults->hasKey("apiVersion"));
    $this->assertTrue($defaults->hasKey("notAValue"));
  }

  public function testGet()
  {
    $defaults = new NullSource();
    $this->assertEquals(null, $defaults->get("apiVersion"));
    $this->assertEquals(null, $defaults->get("weirdThing"));
  }
}
