<?php

declare(strict_types=1);

/**
 * User-set values Config source
 * These values should come from other code
 *
 * Internal to Config - see Scoutapm\Config for the public API.
 */

namespace Scoutapm\Config;

use function array_key_exists;

class UserSettingsSource
{
    /** @var array<string, string|null> */
    private $config;

    public function __construct()
    {
        $this->config = [];
    }

    /**
     * Returns true if this config source knows for certain it has an answer for this key
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
     * @return mixed
     */
    public function get(string $key)
    {
        return $this->config[$key] ?? null;
    }

    public function set(string $key, ?string $value) : void
    {
        $this->config[$key] = $value;
    }
}
