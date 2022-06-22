<?php

declare(strict_types=1);

namespace Scoutapm\Cache;

use Psr\SimpleCache\CacheInterface;

use function array_combine;
use function array_map;
use function is_array;
use function iterator_to_array;

/** @internal */
abstract class DevNullCacheSimpleCache1 implements CacheInterface
{
    /** @inheritDoc */
    public function get($key, $default = null)
    {
        return $default;
    }

    /** @inheritDoc */
    public function set($key, $value, $ttl = null): bool
    {
        return true;
    }

    /** @inheritDoc */
    public function delete($key): bool
    {
        return true;
    }

    public function clear(): bool
    {
        return true;
    }

    /** @inheritDoc */
    public function getMultiple($keys, $default = null)
    {
        if (is_array($keys)) {
            $keysAsArray = $keys;
        } else {
            $keysAsArray = iterator_to_array($keys, false);
        }

        return array_combine(
            $keysAsArray,
            array_map(
                /** @return mixed */
                function (string $key) use ($default) {
                    return $this->get($key, $default);
                },
                $keysAsArray
            )
        );
    }

    /** @inheritDoc */
    public function setMultiple($values, $ttl = null): bool
    {
        return true;
    }

    /** @inheritDoc */
    public function deleteMultiple($keys): bool
    {
        return true;
    }

    /** @inheritDoc */
    public function has($key): bool
    {
        return false;
    }
}
