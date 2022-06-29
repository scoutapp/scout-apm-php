<?php

declare(strict_types=1);

namespace Scoutapm\Cache;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

use function array_combine;
use function array_map;
use function is_array;
use function iterator_to_array;

/** @internal */
abstract class DevNullCacheSimpleCache2And3 implements CacheInterface
{
    /** @inheritDoc */
    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    /** @inheritDoc */
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        return true;
    }

    /** @inheritDoc */
    public function delete(string $key): bool
    {
        return true;
    }

    public function clear(): bool
    {
        return true;
    }

    /** @inheritDoc */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
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
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        return true;
    }

    /** @inheritDoc */
    public function deleteMultiple(iterable $keys): bool
    {
        return true;
    }

    /** @inheritDoc */
    public function has(string $key): bool
    {
        return false;
    }
}
