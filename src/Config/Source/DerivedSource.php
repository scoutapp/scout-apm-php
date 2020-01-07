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
use Scoutapm\Helper\LibcDetection;
use function in_array;
use function php_uname;

/** @internal */
final class DerivedSource implements ConfigSource
{
    private const SUPPORTED_DERIVED_KEYS = [
        ConfigKey::CORE_AGENT_SOCKET_PATH,
        ConfigKey::CORE_AGENT_FULL_NAME,
        ConfigKey::CORE_AGENT_TRIPLE,
    ];

    /** @var Config */
    private $config;

    /** @var LibcDetection */
    private $libcDetection;

    public function __construct(Config $config, LibcDetection $libcDetection)
    {
        $this->config        = $config;
        $this->libcDetection = $libcDetection;
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

    private function architecture() : string
    {
        $unameArch = php_uname('m');

        if (in_array($unameArch, ['i686', 'x86_64'], true)) {
            return $unameArch;
        }

        return 'unknown';
    }

    private function coreAgentTriple() : string
    {
        $platform = 'unknown-linux-' . $this->libcDetection->detect();

        $unamePlatform = php_uname('s');
        if ($unamePlatform === 'Darwin') {
            $platform = 'apple-darwin';
        }

        return $this->architecture() . '-' . $platform;
    }
}
