<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\ScoutApmBundle\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Scoutapm\ScoutApmBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;

/** @covers \Scoutapm\ScoutApmBundle\DependencyInjection\Configuration */
final class ConfigurationTest extends TestCase
{
    public function testConfigTreeBuilderCanParseConfig(): void
    {
        self::assertEquals(
            [
                'scoutapm' => [
                    'name' => 'My Great Application',
                    'monitor' => true,
                    'key' => 'abc123',
                    'log_level' => null,
                    'api_version' => null,
                    'ignore' => null,
                    'application_root' => null,
                    'scm_subdirectory' => null,
                    'revision_sha' => null,
                    'hostname' => null,
                    'core_agent_log_level' => null,
                    'core_agent_log_file' => null,
                    'core_agent_config_file' => null,
                    'core_agent_socket_path' => null,
                    'core_agent_dir' => null,
                    'core_agent_full_name' => null,
                    'core_agent_download_url' => null,
                    'core_agent_launch' => null,
                    'core_agent_download' => null,
                    'core_agent_version' => null,
                    'core_agent_triple' => null,
                    'core_agent_permissions' => null,
                    'disabled_instruments' => null,
                    'log_payload_content' => null,
                    'uri_reporting' => null,
                    'uri_filtered_params' => null,
                    'errors_enabled' => null,
                    'errors_ignored_exceptions' => null,
                    'errors_host' => null,
                    'errors_batch_size' => null,
                    'errors_filtered_params' => null,
                ],
            ],
            (new Processor())->processConfiguration(
                new Configuration(),
                [
                    [
                        'scoutapm' => [
                            'name' => 'My Great Application',
                            'monitor' => true,
                            'key' => 'abc123',
                        ],
                    ],
                ]
            )
        );
    }
}
