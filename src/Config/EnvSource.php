<?php

/**
 * User-set values Config source
 * These values should come from other code
 *
 * Internal to Config - see Scoutapm\Config for the public API.
 */

namespace Scoutapm\Config;

class EnvSource
{
    /**
     * Returns true iff this config source knows for certain it has an answer for this key
     *
     * @return bool
     */
    public function hasKey(string $key) : bool
    {
        if (getEnv($this->envVarName($key))) {
            return true;
        } else {
            return false;
        }
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
        $value = getEnv($this->envVarName($key));

        // Make sure this returns null when not found, instead of getEnv's false.
        if ($value == false) { $value = null; }

        return $value;
    }

    /**
     * undocumented function
     *
     * @return void
     */
    private function envVarName(string $key) : string
    {
        $upper = strtoupper($key);
        return "SCOUT_" . $upper;
    }
}
