<?php

/**
 * Default Values Config source
 *
 * Internal to Config - see Scoutapm\Config for the public API.
 */

namespace Scoutapm\Config;

class DefaultSource
{
    private $defaults;

    public function __construct()
    {
        $this->defaults = $this->getDefaultConfig();
    }

    /**
     * Returns true iff this config source knows for certain it has an answer for this key
     *
     * @return bool
     */
    public function hasKey(string $key) : bool
    {
        return array_key_exists($key, $this->defaults);
    }
    

    /**
     * Returns the value for this configuration key.
     *
     * Only valid if the Source has previously returned "true" to `hasKey`
     *
     * @return The value requested
     */
    public function get(string $key)
    {
        return ($this->defaults[$key]) ?? null;
    }


    /**
     * Returns the value for this configuration key.
     *
     * Only valid if the Source has previously returned "true" to `hasKey`
     *
     * @return The value requested
     */
    private function getDefaultConfig() : array
    {
        return [
            'app_name' => null,
            'key' => null,
            'api_version' => '1.0',
            'socket_location' => '/tmp/core-agent.sock',
            'core_agent_launch' => true,
            'core_agent_download' => true,
            'core_agent_version' => 'latest',
            'core_agent_dir' => '/tmp/scout_apm_core',
            'core_agent_full_name' => 'foo',
            'download_url' => 'https://s3-us-west-1.amazonaws.com/scout-public-downloads/apm_core_agent/release',

        ];
    }
}
