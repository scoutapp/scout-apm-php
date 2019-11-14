<?php

declare(strict_types=1);

/**
 * User-set values Config source
 * These values should come from other code
 *
 * Internal to Config - see Scoutapm\Config for the public API.
 */

namespace Scoutapm\Config\Source;

use Scoutapm\Config\ConfigKey;
use const ARRAY_FILTER_USE_KEY;
use function array_combine;
use function array_filter;
use function array_keys;
use function array_map;
use function getenv;
use function strlen;
use function strpos;
use function strtolower;
use function strtoupper;
use function substr;

/** @internal */
final class EnvSource implements ConfigSource
{
    private const SCOUT_PREFIX = 'SCOUT_';

    /** @inheritDoc */
    public function hasKey(string $key) : bool
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

    private function envVarName(string $key) : string
    {
        return self::SCOUT_PREFIX . strtoupper($key);
    }

    /** @inheritDoc */
    public function asArrayWithSecretsRemoved() : array
    {
        $scoutPrefixedEnvVars = array_filter(
            getenv(),
            /** @param mixed $v */
            static function ($v) : bool {
                return strpos($v, self::SCOUT_PREFIX) === 0;
            },
            ARRAY_FILTER_USE_KEY
        );

        return ConfigKey::filterSecretsFromConfigArray(array_combine(
            array_map(
                static function (string $k) {
                    return strtolower(substr($k, strlen(self::SCOUT_PREFIX)));
                },
                array_keys($scoutPrefixedEnvVars)
            ),
            $scoutPrefixedEnvVars
        ));
    }
}
