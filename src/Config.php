<?php

/**
 * Public API for accessing configuration
 */

namespace Scoutapm;

class Config
{
    private $sources;
    private $userSettingsSource;
    private $agent;

    public function __construct(\Scoutapm\Agent $agent)
    {
        $this->agent = $agent;
        $this->userSettingsSource = new Config\UserSettingsSource();

        $this->sources = [
            new Config\EnvSource(),
            $this->userSettingsSource,
            new Config\DerivedSource($this),
            new Config\DefaultSource(),
            new Config\NullSource(),
        ];
    }


    /**
     * Looks through all available sources for the first that can handle this
     * key, then returns the value from that source.
     */
    public function get(string $key)
    {
        foreach($this->sources as $source) {
            if ($source->hasKey($key)) {
                return $source->get($key);
            }
        }
    }


    /**
     * Sets a value on the inner UserSettingsSource
     *
     * @return void
     */
    public function set(string $key, $value)
    {
        $this->userSettingsSource->set($key, $value);
    }
}
