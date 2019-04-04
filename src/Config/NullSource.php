<?php

/**
 * Null Config source, always knows the key, and its always null
 *
 * Internal to Config - see Scoutapm\Config for the public API.
 */

namespace Scoutapm\Config;

class NullSource
{
    /**
     * Returns true 
     *
     * @return bool (always True)
     */
    public function has_key(string $key) : bool
    {
        return true;
    }
    

    /**
     * Returns the value for this configuration key.
     *
     * Only valid if the Source has previously returned "true" to `has_key`
     *
     * @return The value requested
     */
    public function get(string $key)
    {
        return null;
    }
}
