<?php
namespace Scoutapm\Tests\Config;

use \PHPUnit\Framework\TestCase;
use \Scoutapm\Config\EnvSource;

final class ConfigEnvSourceTest extends TestCase
{
    public function testHasKey()
    {
        $config = new EnvSource();
        $this->assertFalse($config->hasKey("test_case_foo"));

        putenv("SCOUT_TEST_CASE_FOO=thevalue");

        $this->assertTrue($config->hasKey("test_case_foo"));

        // Clean up the var
        putenv('SCOUT_TEST_CASE_FOO');
    }

    public function testGet()
    {
        $config = new EnvSource();
        $this->assertNull($config->get("test_case_bar"));

        putenv("SCOUT_TEST_CASE_BAR=thevalue");

        $this->assertEquals("thevalue", $config->get("test_case_bar"));

        // Clean up the var
        putenv('SCOUT_TEST_CASE_BAR');
    }
}
