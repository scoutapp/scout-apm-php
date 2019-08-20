<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Config;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config\EnvSource;
use function putenv;

final class EnvSourceTest extends TestCase
{
    public function testHasKey() : void
    {
        $config = new EnvSource();
        $this->assertFalse($config->hasKey('test_case_foo'));

        putenv('SCOUT_TEST_CASE_FOO=thevalue');

        $this->assertTrue($config->hasKey('test_case_foo'));

        // Clean up the var
        putenv('SCOUT_TEST_CASE_FOO');
    }

    public function testGet() : void
    {
        $config = new EnvSource();
        $this->assertNull($config->get('test_case_bar'));

        putenv('SCOUT_TEST_CASE_BAR=thevalue');

        $this->assertEquals('thevalue', $config->get('test_case_bar'));

        // Clean up the var
        putenv('SCOUT_TEST_CASE_BAR');
    }
}
