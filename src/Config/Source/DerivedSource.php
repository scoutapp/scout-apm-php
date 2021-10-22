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
    ];

    /** @var Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function hasKey(string $key): bool
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

    private function socketPath(): string
    {
        return 'tcp://127.0.0.1:6590';
    }

    private function coreAgentFullName(): string
    {
        $name    = 'scout_apm_core';
        $version = $this->config->get(ConfigKey::CORE_AGENT_VERSION);
        $triple  = $this->config->get(ConfigKey::CORE_AGENT_TRIPLE);

        return $name . '-' . $version . '-' . $triple;
    }

    private function architecture(): string
    {
        $unameArch = php_uname('m');

        /**
         * On new M1 Macs (arm64), we can still use x86_64 build
         *
         * @link https://github.com/scoutapp/scout-apm-php/issues/239
         */
        if ($unameArch === 'arm64') {
            return 'x86_64';
        }

        /**
         * aarch64 reported for some Linux environments, e.g. "AWS Graviton"
         *
         * @link https://github.com/scoutapp/scout-apm-php/issues/239
         */
        if (in_array($unameArch, ['i686', 'x86_64', 'aarch64'], true)) {
            return $unameArch;
        }

        return 'unknown';
    }

    private function coreAgentTriple(): string
    {
        /**
         * Since the `musl`-based agent should work on `glibc`-based systems, we can hard-code this now.
         *
         * @link https://github.com/scoutapp/scout-apm-php/issues/166
         */
        $platform = 'unknown-linux-musl';

        $unamePlatform = php_uname('s');
        if ($unamePlatform === 'Darwin') {
            $platform = 'apple-darwin';
        }

        return $this->architecture() . '-' . $platform;
    }
}
