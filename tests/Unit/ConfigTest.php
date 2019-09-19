<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config;
use function putenv;

/** @covers \Scoutapm\Config*/
final class ConfigTest extends TestCase
{
    public function testGetFallsBackToDefaults() : void
    {
        $config = new Config();

        // Provided by the DefaultConfig
        self::assertSame('1.0', $config->get('api_version'));
    }

    public function testUserSettingsOverridesDefaults() : void
    {
        $config = new Config();
        $config->set('api_version', 'viauserconf');

        self::assertSame('viauserconf', $config->get('api_version'));
    }

    public function testEnvOverridesAll() : void
    {
        $config = new Config();

        // Set a user config. This won't be looked up
        $config->set('api_version', 'viauserconf');

        // And set the env var
        putenv('SCOUT_API_VERSION=viaenvvar');

        self::assertSame('viaenvvar', $config->get('api_version'));

        putenv('SCOUT_API_VERSION');
    }

    public function testBooleanCoercionOfMonitor() : void
    {
        $config = new Config();

        // Set a user config. This won't be looked up
        $config->set('monitor', 'true');
        self::assertTrue($config->get('monitor'));
    }

    public function testJSONCoercionOfIgnore() : void
    {
        $config = new Config();

        // Set a user config. This won't be looked up
        $config->set('ignore', '["/foo", "/bar"]');
        self::assertSame(['/foo', '/bar'], $config->get('ignore'));
    }

    public function testIgnoreDefaultsToEmptyArray() : void
    {
        self::assertSame([], (new Config())->get('ignore'));
    }
}
