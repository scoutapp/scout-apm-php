<?php

declare(strict_types=1);

/**
 * Default Values Config source
 *
 * Internal to Config - see Scoutapm\Config for the public API.
 */

namespace Scoutapm\Config\Source;

use function array_key_exists;

/** @internal */
class DefaultSource
{
    /** @var array<string, (string|bool|array<int, string>)> */
    private $defaults;

    public function __construct()
    {
        $this->defaults = $this->getDefaultConfig();
    }

    /**
     * Returns true iff this config source knows for certain it has an answer for this key
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
     * @return string|bool|array<int, string>|null
     */
    public function get(string $key)
    {
        return $this->defaults[$key] ?? null;
    }

    /**
     * Returns the value for this configuration key.
     *
     * Only valid if the Source has previously returned "true" to `hasKey`
     *
     * @return array<string, (string|bool|array<int, string>)>
     */
    private function getDefaultConfig() : array
    {
        return [
            'api_version' => '1.0',
            'core_agent_dir' => '/tmp/scout_apm_core',
            'core_agent_download' => true,
            'core_agent_launch' => true,
            'core_agent_version' => 'latest',
            'download_url' => 'https://s3-us-west-1.amazonaws.com/scout-public-downloads/apm_core_agent/release',
            'monitor' => false,
            'ignore' => [],
        ];
    }
}
