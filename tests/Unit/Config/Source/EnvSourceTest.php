<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Config\Source;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config\Source\EnvSource;

use function putenv;

/** @covers \Scoutapm\Config\Source\EnvSource */
final class EnvSourceTest extends TestCase
{
    public function testHasKey(): void
    {
        $config = new EnvSource();
        self::assertFalse($config->hasKey('test_case_foo'));

        putenv('SCOUT_TEST_CASE_FOO=thevalue');

        self::assertTrue($config->hasKey('test_case_foo'));

        // Clean up the var
        putenv('SCOUT_TEST_CASE_FOO');
    }

    public function testGet(): void
    {
        $config = new EnvSource();
        self::assertNull($config->get('test_case_bar'));

        putenv('SCOUT_TEST_CASE_BAR=thevalue');

        self::assertSame('thevalue', $config->get('test_case_bar'));

        // Clean up the var
        putenv('SCOUT_TEST_CASE_BAR');
    }
}
