<?php

/**
 * Derived Config source
 * The values here are "defaults" that may rely on other configuration settings.
 * For instance, setting "core_agent_version" cascades down to "core_agent_download_url" and so on.
 *
 * Internal to Config - see Scoutapm\Config for the public API.
 */

namespace Scoutapm\Config;

class DerivedSource
{
    private $config;
    private $handlers;

    /**
     * @param $config - the global config var, for looking up the components of derived configs
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->handlers = [
            "core_agent_triple",
            "core_agent_full_name",
            "socket_path",
            "testing", // Used for testing. Should be removed and test converted to a real value once we have one.
        ];
    }
    
    /**
     * Returns true iff this config source knows for certain it has an answer for this key
     *
     * @return bool
     */
    public function hasKey(string $key) : bool
    {
        return in_array($key, $this->handlers);
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
        // Whitelisted keys only
        if (! $this->hasKey($key)) {
            return null;
        }

        return $this->$key();
    }

    ///////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////
    // Derived Keys below this spot.
    //
    public function socket_path()
    {
        $dir = $this->config->get("core_agent_dir");
        $fullName = $this->config->get("core_agent_full_name");

        return $dir . "/" . $fullName . "/core-agent.sock";
    }

    public function core_agent_full_name()
    {
        $name = "scout_apm_core";
        $version = $this->config->get("core_agent_version");
        $triple = $this->config->get("core_agent_triple");
        return $name . "-" . $version . "-" . $triple;
    }

    public function core_agent_triple()
    {
        $arch = "i686";
        $platform = "unknown-linux-gnu";
        return $arch . "-" . $platform;
    }

    /**
    * Used for testing this class, not a real configuration.
    * We should remove this and adjust the test once we have a real use of this class.
     */
    private function testing()
    {
        $version = $this->config->get("api_version");
        return "derived api version: " . $version;
    }
}
