<?php
namespace Scoutapm\UnitTests\Config;

use \PHPUnit\Framework\TestCase;
use \Scoutapm\Agent;
use \Scoutapm\Config;
use \Scoutapm\Config\DerivedSource;

/** @covers \Scoutapm\Config\DerivedSource */
final class DerivedSourceTest extends TestCase
{
    public function testHasKey()
    {
        $config = new Config(new Agent());
        $derived = new DerivedSource($config);

        $this->assertTrue($derived->hasKey("testing"));
        $this->assertFalse($derived->hasKey("is_array"));
    }

    public function testGet()
    {
        $config = new Config(new Agent());
        $derived = new DerivedSource($config);

        $this->assertEquals("derived api version: 1.0", $derived->get("testing"));
    }
}
