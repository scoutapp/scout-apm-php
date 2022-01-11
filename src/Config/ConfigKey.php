<?php

declare(strict_types=1);

namespace Scoutapm\Config;

use function array_combine;
use function array_keys;
use function array_map;
use function in_array;

abstract class ConfigKey
{
    public const MONITORING_ENABLED          = 'monitor';
    public const APPLICATION_NAME            = 'name';
    public const APPLICATION_KEY             = 'key';
    public const LOG_LEVEL                   = 'log_level';
    public const LOG_PAYLOAD_CONTENT         = 'log_payload_content';
    public const API_VERSION                 = 'api_version';
    public const IGNORED_ENDPOINTS           = 'ignore';
    public const APPLICATION_ROOT            = 'application_root';
    public const SCM_SUBDIRECTORY            = 'scm_subdirectory';
    public const REVISION_SHA                = 'revision_sha';
    public const HOSTNAME                    = 'hostname';
    public const DISABLED_INSTRUMENTS        = 'disabled_instruments';
    public const CORE_AGENT_LOG_LEVEL        = 'core_agent_log_level';
    public const CORE_AGENT_LOG_FILE         = 'core_agent_log_file';
    public const CORE_AGENT_CONFIG_FILE      = 'core_agent_config_file';
    public const CORE_AGENT_SOCKET_PATH      = 'core_agent_socket_path';
    public const CORE_AGENT_DIRECTORY        = 'core_agent_dir';
    public const CORE_AGENT_FULL_NAME        = 'core_agent_full_name';
    public const CORE_AGENT_DOWNLOAD_URL     = 'core_agent_download_url';
    public const CORE_AGENT_LAUNCH_ENABLED   = 'core_agent_launch';
    public const CORE_AGENT_DOWNLOAD_ENABLED = 'core_agent_download';
    public const CORE_AGENT_VERSION          = 'core_agent_version';
    public const CORE_AGENT_TRIPLE           = 'core_agent_triple';
    public const CORE_AGENT_PERMISSIONS      = 'core_agent_permissions';
    public const FRAMEWORK                   = 'framework';
    public const FRAMEWORK_VERSION           = 'framework_version';
    public const URI_REPORTING               = 'uri_reporting';
    public const URI_FILTERED_PARAMETERS     = 'uri_filtered_params';
    public const ERRORS_ENABLED              = 'errors_enabled';
    public const ERRORS_IGNORED_EXCEPTIONS   = 'errors_ignored_exceptions';
    public const ERRORS_HOST                 = 'errors_host';
    public const ERRORS_BATCH_SIZE           = 'errors_batch_size';
    public const ERRORS_FILTERED_PARAMETERS  = 'errors_filtered_params';

    private const SECRET_CONFIGURATIONS = [self::APPLICATION_KEY];

    /** @internal */
    public const URI_REPORTING_PATH_ONLY = 'path';
    /** @internal */
    public const URI_REPORTING_FULL_PATH = 'full_path';
    /** @internal */
    public const URI_REPORTING_FILTERED = 'filtered_params';

    /** @return string[] */
    public static function allConfigurationKeys(): array
    {
        return [
            self::MONITORING_ENABLED,
            self::APPLICATION_NAME,
            self::APPLICATION_KEY,
            self::LOG_LEVEL,
            self::LOG_PAYLOAD_CONTENT,
            self::API_VERSION,
            self::IGNORED_ENDPOINTS,
            self::APPLICATION_ROOT,
            self::SCM_SUBDIRECTORY,
            self::REVISION_SHA,
            self::HOSTNAME,
            self::DISABLED_INSTRUMENTS,
            self::CORE_AGENT_LOG_LEVEL,
            self::CORE_AGENT_LOG_FILE,
            self::CORE_AGENT_CONFIG_FILE,
            self::CORE_AGENT_SOCKET_PATH,
            self::CORE_AGENT_DIRECTORY,
            self::CORE_AGENT_FULL_NAME,
            self::CORE_AGENT_DOWNLOAD_URL,
            self::CORE_AGENT_LAUNCH_ENABLED,
            self::CORE_AGENT_DOWNLOAD_ENABLED,
            self::CORE_AGENT_VERSION,
            self::CORE_AGENT_TRIPLE,
            self::CORE_AGENT_PERMISSIONS,
            self::URI_REPORTING,
            self::URI_FILTERED_PARAMETERS,
            self::ERRORS_ENABLED,
            self::ERRORS_IGNORED_EXCEPTIONS,
            self::ERRORS_HOST,
            self::ERRORS_BATCH_SIZE,
            self::ERRORS_FILTERED_PARAMETERS,
        ];
    }

    /**
     * @param mixed[] $configArray
     *
     * @return mixed[]
     *
     * @psalm-param array<string, mixed> $configArray
     * @psalm-return array<string, mixed>
     */
    public static function filterSecretsFromConfigArray(array $configArray): array
    {
        return array_combine(
            array_keys($configArray),
            array_map(
                /**
                 * @param mixed $v
                 *
                 * @return mixed
                 */
                static function (string $k, $v) {
                    if (in_array($k, self::SECRET_CONFIGURATIONS, true)) {
                        return '<redacted>';
                    }

                    return $v;
                },
                array_keys($configArray),
                $configArray
            )
        );
    }
}
