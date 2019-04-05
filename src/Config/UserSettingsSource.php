<?php

/**
 * User-set values Config source
 * These values should come from other code
 *
 * Internal to Config - see Scoutapm\Config for the public API.
 */

namespace Scoutapm\Config;

class UserSettingsSource
{
    private $config;

    public function __construct()
    {
        $this->config = [];
    }

    /**
     * Returns true iff this config source knows for certain it has an answer for this key
     *
     * @return bool
     */
    public function hasKey(string $key) : bool
    {
        return array_key_exists($key, $this->config);
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
        return ($this->config[$key]) ?? null;
    }


    public function set($key, $value)
    {
        $this->config[$key] = $value;
    }
}
