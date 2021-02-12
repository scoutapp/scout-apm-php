<?php

declare(strict_types=1);

/**
 * User-set values Config source
 * These values should come from other code
 *
 * Internal to Config - see Scoutapm\Config for the public API.
 */

namespace Scoutapm\Config\Source;

use function getenv;
use function strtoupper;

/** @internal */
final class EnvSource implements ConfigSource
{
    private const SCOUT_PREFIX = 'SCOUT_';

    public function hasKey(string $key): bool
    {
        return getenv($this->envVarName($key)) !== false;
    }

    /** @inheritDoc */
    public function get(string $key)
    {
        $value = getenv($this->envVarName($key));

        // Make sure this returns null when not found, instead of getEnv's false.
        if ($value === false) {
            $value = null;
        }

        return $value;
    }

    private function envVarName(string $key): string
    {
        return self::SCOUT_PREFIX . strtoupper($key);
    }
}
