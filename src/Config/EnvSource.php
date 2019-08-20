<?php

declare(strict_types=1);

/**
 * User-set values Config source
 * These values should come from other code
 *
 * Internal to Config - see Scoutapm\Config for the public API.
 */

namespace Scoutapm\Config;

use function getenv;
use function strtoupper;

class EnvSource
{
    /**
     * Returns true iff this config source knows for certain it has an answer for this key
     */
    public function hasKey(string $key) : bool
    {
        return getenv($this->envVarName($key)) !== false;
    }

    /**
     * Returns the value for this configuration key.
     *
     * Only valid if the Source has previously returned "true" to `hasKey`
     */
    public function get(string $key) : ?string
    {
        $value = getenv($this->envVarName($key));

        // Make sure this returns null when not found, instead of getEnv's false.
        if ($value === false) {
            $value = null;
        }

        return $value;
    }

    private function envVarName(string $key) : string
    {
        $upper = strtoupper($key);

        return 'SCOUT_' . $upper;
    }
}
