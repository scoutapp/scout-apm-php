<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Config\Source;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config\Source\EnvSource;
use function putenv;

/** @covers \Scoutapm\Config\Source\EnvSource */
final class EnvSourceTest extends TestCase
{
    public function testHasKey() : void
    {
        $config = new EnvSource();
        self::assertFalse($config->hasKey('test_case_foo'));

        putenv('SCOUT_TEST_CASE_FOO=thevalue');

        self::assertTrue($config->hasKey('test_case_foo'));

        // Clean up the var
        putenv('SCOUT_TEST_CASE_FOO');
    }

    public function testGet() : void
    {
        $config = new EnvSource();
        self::assertNull($config->get('test_case_bar'));

        putenv('SCOUT_TEST_CASE_BAR=thevalue');

        self::assertSame('thevalue', $config->get('test_case_bar'));

        // Clean up the var
        putenv('SCOUT_TEST_CASE_BAR');
    }

    public function testAsArray() : void
    {
        putenv('SCOUT_KEY=secret key');
        putenv('SCOUT_NAME=My App');

        $configArray = (new EnvSource())->asArrayWithSecretsRemoved();

        self::assertArrayHasKey('key', $configArray);
        self::assertSame('<redacted>', $configArray['key']);
        self::assertArrayHasKey('name', $configArray);
        self::assertSame('My App', $configArray['name']);

        putenv('SCOUT_KEY');
        putenv('SCOUT_NAME');
    }
}
