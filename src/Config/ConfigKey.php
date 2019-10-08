<?php

declare(strict_types=1);

namespace Scoutapm\Config;

abstract class ConfigKey
{
    public const MONITORING_ENABLED          = 'monitor';
    public const APPLICATION_NAME            = 'name';
    public const APPLICATION_KEY             = 'key';
    public const LOG_LEVEL                   = 'log_level';
    public const API_VERSION                 = 'api_version';
    public const IGNORED_ENDPOINTS           = 'ignore';
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
}
