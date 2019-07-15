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
    private $coercions;

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

        $this->coercions = [
            "monitor" => Config\BoolCoercion::class,
        ];
    }


    /**
     * Looks through all available sources for the first that can handle this
     * key, then returns the value from that source.
     */
    public function get(string $key)
    {
        foreach ($this->sources as $source) {
            if ($source->hasKey($key)) {
                $value = $source->get($key);
                break;
            }
        }

        if (array_key_exists($key, $this->coercions)) {
            $coercion = new $this->coercions[$key];
            $value = $coercion->coerce($value);
        }

        return $value;
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
