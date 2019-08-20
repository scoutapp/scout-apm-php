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
use function php_uname;

/** @internal */
class DerivedSource
{
    /** @var Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Returns true if this config source knows for certain it has an answer for this key
     */
    public function hasKey(string $key) : bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Returns the value for this configuration key.
     *
     * Only valid if the Source has previously returned "true" to `hasKey`
     *
     * @return mixed
     */
    public function get(string $key)
    {
        switch ($key) {
            case 'socket_path':
                return $this->socketPath();
            case 'core_agent_full_name':
                return $this->coreAgentFullName();
            case 'core_agent_triple':
                return $this->coreAgentTriple();
            case 'testing':
                return $this->testing();
        }

        return null;
    }

    private function socketPath() : string
    {
        $dir      = $this->config->get('core_agent_dir');
        $fullName = $this->config->get('core_agent_full_name');

        return $dir . '/' . $fullName . '/core-agent.sock';
    }

    private function coreAgentFullName() : string
    {
        $name    = 'scout_apm_core';
        $version = $this->config->get('core_agent_version');
        $triple  = $this->config->get('core_agent_triple');

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
        $version = $this->config->get('api_version');

        return 'derived api version: ' . $version;
    }
}
