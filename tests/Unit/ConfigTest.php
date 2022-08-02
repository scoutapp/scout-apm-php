<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;

use function json_encode;
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

    public function testFilteredParamsAreParsedToArrayFromJson(): void
    {
        $config = new Config();

        putenv('SCOUT_URI_FILTERED_PARAMS=["a","b"]');

        self::assertEquals(['a', 'b'], $config->get(ConfigKey::URI_FILTERED_PARAMETERS));

        putenv('SCOUT_URI_FILTERED_PARAMS');
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

    public function testDefinedCoercions(): void
    {
        $generateStringableClass =
            static function (string $theString): object {
                return new class ($theString) {
                    /** @var string */
                    private $theString;

                    public function __construct(string $theString)
                    {
                        $this->theString = $theString;
                    }

                    public function __toString(): string
                    {
                        return $this->theString;
                    }
                };
            };

        self::assertSame(
            [
                'monitor' => true,
                'name' => null,
                'key' => '<redacted>',
                'log_level' => 'log_level_value',
                'log_payload_content' => false,
                'api_version' => '1.1',
                'ignore' => ['a','b'],
                'application_root' => null,
                'scm_subdirectory' => null,
                'revision_sha' => null,
                'hostname' => null,
                'disabled_instruments' => ['a','b'],
                'core_agent_log_level' => null,
                'core_agent_log_file' => null,
                'core_agent_config_file' => null,
                'core_agent_socket_path' => 'core_agent_socket_path_value',
                'core_agent_dir' => 'core_agent_dir_value',
                'core_agent_full_name' => 'core_agent_full_name_value',
                'core_agent_download_url' => 'core_agent_download_url_value',
                'core_agent_launch' => true,
                'core_agent_download' => true,
                'core_agent_version' => 'core_agent_version_value',
                'core_agent_triple' => 'core_agent_triple_value',
                'core_agent_permissions' => 0777,
                'uri_reporting' => 'uri_reporting_value',
                'uri_filtered_params' => ['a','b'],
                'errors_enabled' => false,
                'errors_ignored_exceptions' => ['a','b'],
                'errors_host' => 'errors_host_value',
                'errors_batch_size' => 3,
                'errors_filtered_params' => ['a','b'],
            ],
            Config::fromArray([
                ConfigKey::MONITORING_ENABLED => 'true',
                ConfigKey::LOG_PAYLOAD_CONTENT => 'false',
                ConfigKey::ERRORS_ENABLED => '0',
                ConfigKey::CORE_AGENT_DOWNLOAD_ENABLED => '1',
                ConfigKey::CORE_AGENT_LAUNCH_ENABLED => '1',
                ConfigKey::IGNORED_ENDPOINTS => json_encode(['a', 'b']),
                ConfigKey::DISABLED_INSTRUMENTS => json_encode(['a', 'b']),
                ConfigKey::URI_FILTERED_PARAMETERS => json_encode(['a', 'b']),
                ConfigKey::ERRORS_IGNORED_EXCEPTIONS => json_encode(['a', 'b']),
                ConfigKey::ERRORS_FILTERED_PARAMETERS => json_encode(['a', 'b']),
                ConfigKey::CORE_AGENT_PERMISSIONS => '511',
                ConfigKey::ERRORS_BATCH_SIZE => 3,
                ConfigKey::API_VERSION => 1.1,
                ConfigKey::CORE_AGENT_DIRECTORY => $generateStringableClass('core_agent_dir_value'),
                ConfigKey::CORE_AGENT_VERSION => $generateStringableClass('core_agent_version_value'),
                ConfigKey::CORE_AGENT_DOWNLOAD_URL => $generateStringableClass('core_agent_download_url_value'),
                ConfigKey::LOG_LEVEL => $generateStringableClass('log_level_value'),
                ConfigKey::ERRORS_HOST => $generateStringableClass('errors_host_value'),
                ConfigKey::URI_REPORTING => $generateStringableClass('uri_reporting_value'),
                ConfigKey::CORE_AGENT_SOCKET_PATH => $generateStringableClass('core_agent_socket_path_value'),
                ConfigKey::CORE_AGENT_FULL_NAME => $generateStringableClass('core_agent_full_name_value'),
                ConfigKey::CORE_AGENT_TRIPLE => $generateStringableClass('core_agent_triple_value'),
            ])->asArrayWithSecretsRemoved()
        );
    }
}
