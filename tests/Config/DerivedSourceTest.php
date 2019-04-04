<?php
namespace Scoutapm\Tests\Config;

use \Scoutapm\Config\DerivedSource;
use \Scoutapm\Config;
use \PHPUnit\Framework\TestCase;

final class ConfigDerivedSourceTest extends TestCase {
  public function testHasKey() {
    $config = new Config();
    $derived = new DerivedSource($config);

    $this->assertTrue($derived->has_key("testing"));
    $this->assertFalse($derived->has_key("is_array"));
  }

  public function testGet()
  {
    $config = new Config();
    $derived = new DerivedSource($config);

    $this->assertEquals("derived api version: 1.0", $derived->get("testing"));
  }
}
