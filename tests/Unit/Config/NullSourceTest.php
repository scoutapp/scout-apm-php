<?php
namespace Scoutapm\UnitTests\Config;

use \PHPUnit\Framework\TestCase;
use \Scoutapm\Config\NullSource;

final class ConfigNullSourceTest extends TestCase
{
    public function testHasKey()
    {
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
