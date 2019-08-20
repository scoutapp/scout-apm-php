<?php

declare(strict_types=1);

/**
 * Public API for accessing configuration
 */

namespace Scoutapm;

use Scoutapm\Config\BoolCoercion;
use Scoutapm\Config\DefaultSource;
use Scoutapm\Config\DerivedSource;
use Scoutapm\Config\EnvSource;
use Scoutapm\Config\JSONCoercion;
use Scoutapm\Config\NullSource;
use Scoutapm\Config\UserSettingsSource;
use function array_key_exists;

class Config
{
    /** @var array<int, EnvSource|UserSettingsSource|DerivedSource|DefaultSource|NullSource> */
    private $sources;

    /** @var UserSettingsSource */
    private $userSettingsSource;

    /**
     * @var array<string, string>
     * @psalm-var array<string, BoolCoercion::class|JSONCoercion::class>
     */
    private $coercions;

    public function __construct(Agent $agent)
    {
        $this->userSettingsSource = new UserSettingsSource();

        $this->sources = [
            new EnvSource(),
            $this->userSettingsSource,
            new DerivedSource($this),
            new DefaultSource(),
            new NullSource(),
        ];

        $this->coercions = [
            'monitor' => BoolCoercion::class,
            'ignore' => JSONCoercion::class,
        ];
    }

    /**
     * Looks through all available sources for the first that can handle this
     * key, then returns the value from that source.
     *
     * @return mixed
     */
    public function get(string $key)
    {
        foreach ($this->sources as $source) {
            if ($source->hasKey($key)) {
                $value = $source->get($key);
                break;
            }
        }

        if (! isset($value)) {
            return null;
        }

        if (array_key_exists($key, $this->coercions)) {
            /** @var JSONCoercion|BoolCoercion $coercion */
            $coercion = new $this->coercions[$key]();
            $value    = $coercion->coerce($value);
        }

        return $value ?? null;
    }

    /**
     * Sets a value on the inner UserSettingsSource
     *
     * @param mixed $value
     */
    public function set(string $key, $value) : void
    {
        $this->userSettingsSource->set($key, $value);
    }
}
