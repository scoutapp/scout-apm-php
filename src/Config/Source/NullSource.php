<?php

declare(strict_types=1);

/**
 * Null Config source, always knows the key, and its always null
 *
 * Internal to Config - see Scoutapm\Config for the public API.
 */

namespace Scoutapm\Config\Source;

/** @internal */
final class NullSource implements ConfigSource
{
    public function hasKey(string $key): bool
    {
        return true;
    }

    /** @inheritDoc */
    public function get(string $key)
    {
        return null;
    }
}
