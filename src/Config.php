<?php

declare(strict_types=1);

/**
 * Public API for accessing configuration
 */

namespace Scoutapm;

use Psr\Log\LogLevel;
use Scoutapm\Config\ConfigKey;
use Scoutapm\Config\Source\DefaultSource;
use Scoutapm\Config\Source\DerivedSource;
use Scoutapm\Config\Source\EnvSource;
use Scoutapm\Config\Source\NullSource;
use Scoutapm\Config\Source\UserSettingsSource;
use Scoutapm\Config\TypeCoercion\CoerceBoolean;
use Scoutapm\Config\TypeCoercion\CoerceJson;
use Scoutapm\Config\TypeCoercion\CoerceType;

use function array_combine;
use function array_key_exists;
use function array_map;

// @todo needs interface
class Config
{
    /** @internal */
    public const DEFAULT_LOG_LEVEL = LogLevel::DEBUG;

    /** @var array<int, (EnvSource|UserSettingsSource|DerivedSource|DefaultSource|NullSource)> */
    private $sources;

    /** @var UserSettingsSource */
    private $userSettingsSource;

    /** @var CoerceType[]|array<string, CoerceType> */
    private $coercions;

    public function __construct()
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
            ConfigKey::MONITORING_ENABLED => new CoerceBoolean(),
            ConfigKey::IGNORED_ENDPOINTS => new CoerceJson(),
            ConfigKey::DISABLED_INSTRUMENTS => new CoerceJson(),
            ConfigKey::LOG_PAYLOAD_CONTENT => new CoerceBoolean(),
            ConfigKey::URI_FILTERED_PARAMETERS => new CoerceJson(),
            ConfigKey::ERRORS_ENABLED => new CoerceBoolean(),
            ConfigKey::ERRORS_IGNORED_EXCEPTIONS => new CoerceJson(),
        ];
    }

    /** @param mixed[]|array<string, mixed> $config */
    public static function fromArray(array $config = []): self
    {
        $instance = new self();

        foreach ($config as $key => $value) {
            $instance->set($key, $value);
        }

        return $instance;
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
            $value = $this->coercions[$key]->coerce($value);
        }

        return $value ?? null;
    }

    /**
     * Sets a value on the inner UserSettingsSource
     *
     * @param mixed $value
     */
    public function set(string $key, $value): void
    {
        $this->userSettingsSource->set($key, $value);
    }

    /**
     * Return the configuration **WITH ALL SECRETS REMOVED**. This must never return "secrets" (such as API
     * keys).
     *
     * @return mixed[]
     *
     * @psalm-return array<string, mixed>
     */
    public function asArrayWithSecretsRemoved(): array
    {
        $keys = ConfigKey::allConfigurationKeys();

        return ConfigKey::filterSecretsFromConfigArray(array_combine(
            $keys,
            array_map(
                /** @return mixed */
                function (string $key) {
                    return $this->get($key);
                },
                $keys
            )
        ));
    }
}
