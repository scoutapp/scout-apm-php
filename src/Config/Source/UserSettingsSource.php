<?php

declare(strict_types=1);

/**
 * User-set values Config source
 * These values should come from other code
 *
 * Internal to Config - see Scoutapm\Config for the public API.
 */

namespace Scoutapm\Config\Source;

use function array_key_exists;

/** @internal */
final class UserSettingsSource implements ConfigSource
{
    /** @var array<string, mixed> */
    private $config;

    public function __construct()
    {
        $this->config = [];
    }

    public function hasKey(string $key): bool
    {
        return array_key_exists($key, $this->config);
    }

    /** @inheritDoc */
    public function get(string $key)
    {
        return $this->config[$key] ?? null;
    }

    /** @param mixed $value */
    public function set(string $key, $value): void
    {
        $this->config[$key] = $value;
    }
}
