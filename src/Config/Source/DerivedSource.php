<?php

declare(strict_types=1);

/**
 * Derived Config source
 * The values here are "defaults" that may rely on other configuration settings.
 * For instance, setting "core_agent_version" cascades down to "core_agent_download_url" and so on.
 *
 * Internal to Config - see Scoutapm\Config for the public API.
 */

namespace Scoutapm\Config\Source;

use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;
use function in_array;
use function php_uname;

/** @internal */
final class DerivedSource implements ConfigSource
{
    private const SUPPORTED_DERIVED_KEYS = [
        ConfigKey::CORE_AGENT_SOCKET_PATH,
        ConfigKey::CORE_AGENT_FULL_NAME,
        ConfigKey::CORE_AGENT_TRIPLE,
        'testing',
    ];

    /** @var Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /** @inheritDoc */
    public function hasKey(string $key) : bool
    {
        return in_array($key, self::SUPPORTED_DERIVED_KEYS, true);
    }

    /** @inheritDoc */
    public function get(string $key)
    {
        switch ($key) {
            case ConfigKey::CORE_AGENT_SOCKET_PATH:
                return $this->socketPath();
            case ConfigKey::CORE_AGENT_FULL_NAME:
                return $this->coreAgentFullName();
            case ConfigKey::CORE_AGENT_TRIPLE:
                return $this->coreAgentTriple();
            case 'testing':
                return $this->testing();
        }

        return null;
    }

    private function socketPath() : string
    {
        $dir      = $this->config->get(ConfigKey::CORE_AGENT_DIRECTORY);
        $fullName = $this->config->get(ConfigKey::CORE_AGENT_FULL_NAME);

        return $dir . '/' . $fullName . '/scout-agent.sock';
    }

    private function coreAgentFullName() : string
    {
        $name    = 'scout_apm_core';
        $version = $this->config->get(ConfigKey::CORE_AGENT_VERSION);
        $triple  = $this->config->get(ConfigKey::CORE_AGENT_TRIPLE);

        return $name . '-' . $version . '-' . $triple;
    }

    private function coreAgentTriple() : string
    {
        $arch      = 'unknown';
        $unameArch = php_uname('m');
        if ($unameArch === 'i686') {
            $arch = 'i686';
        }
        if ($unameArch === 'x86_64') {
            $arch = 'x86_64';
        }

        $platform      = 'unknown-linux-gnu';
        $unamePlatform = php_uname('s');
        if ($unamePlatform === 'Darwin') {
            $platform = 'apple-darwin';
        }

        return $arch . '-' . $platform;
    }

    /**
     * Used for testing this class, not a real configuration.
     * We should remove this and adjust the test once we have a real use of this class.
     */
    private function testing() : string
    {
        $version = $this->config->get(ConfigKey::API_VERSION);

        return 'derived api version: ' . $version;
    }
}
