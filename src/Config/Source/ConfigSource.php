<?php

declare(strict_types=1);

namespace Scoutapm\Config\Source;

interface ConfigSource
{
    /**
     * Returns true if this config source knows for certain it has an answer for this key
     */
    public function hasKey(string $key) : bool;

    /**
     * Returns the value for this configuration key.
     *
     * Only valid if the Source has previously returned "true" to `hasKey`
     *
     * @return mixed
     */
    public function get(string $key);

    /**
     * Return this configuration source **WITH ALL SECRETS REMOVED**. This must never return "secrets" (such as API
     * keys).
     *
     * A filtering function exists in \Scoutapm\Config\ConfigKey::filterSecretsFromConfigArray which is recommended
     * for implementations to filter secrets.
     *
     * @return mixed[]
     *
     * @psalm-return array<string, mixed>
     */
    public function asArrayWithSecretsRemoved() : array;
}
