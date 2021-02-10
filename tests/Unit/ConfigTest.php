<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;

use function putenv;

/** @covers \Scoutapm\Config*/
final class ConfigTest extends TestCase
{
    public function testGetFallsBackToDefaults(): void
    {
        $config = new Config();

        // Provided by the DefaultConfig
        self::assertSame('1.0', $config->get(ConfigKey::API_VERSION));
    }

    public function testUserSettingsOverridesDefaults(): void
    {
        $config = new Config();
        $config->set(ConfigKey::API_VERSION, 'viauserconf');

        self::assertSame('viauserconf', $config->get(ConfigKey::API_VERSION));
    }

    public function testEnvOverridesAll(): void
    {
        $config = new Config();

        // Set a user config. This won't be looked up
        $config->set(ConfigKey::API_VERSION, 'viauserconf');

        // And set the env var
        putenv('SCOUT_API_VERSION=viaenvvar');

        self::assertSame('viaenvvar', $config->get(ConfigKey::API_VERSION));

        putenv('SCOUT_API_VERSION');
    }

    public function testBooleanCoercionOfMonitor(): void
    {
        $config = new Config();

        // Set a user config. This won't be looked up
        $config->set(ConfigKey::MONITORING_ENABLED, 'true');
        self::assertTrue($config->get(ConfigKey::MONITORING_ENABLED));
    }

    public function testJSONCoercionOfIgnore(): void
    {
        $config = new Config();

        // Set a user config. This won't be looked up
        $config->set(ConfigKey::IGNORED_ENDPOINTS, '["/foo", "/bar"]');
        self::assertSame(['/foo', '/bar'], $config->get(ConfigKey::IGNORED_ENDPOINTS));
    }

    public function testIgnoreDefaultsToEmptyArray(): void
    {
        self::assertSame([], (new Config())->get(ConfigKey::IGNORED_ENDPOINTS));
    }

    public function testAsArray(): void
    {
        $configArray = Config::fromArray([
            ConfigKey::APPLICATION_NAME => 'My App',
            ConfigKey::APPLICATION_KEY => 'secret key',
            ConfigKey::MONITORING_ENABLED => true,
        ])->asArrayWithSecretsRemoved();

        self::assertArrayHasKey('name', $configArray);
        self::assertSame('My App', $configArray['name']);
        self::assertArrayHasKey('key', $configArray);
        self::assertSame('<redacted>', $configArray['key']);
        self::assertArrayHasKey('monitor', $configArray);
        self::assertTrue($configArray['monitor']);
    }
}
