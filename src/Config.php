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
use Scoutapm\Config\TypeCoercion\CoerceInt;
use Scoutapm\Config\TypeCoercion\CoerceJson;
use Scoutapm\Config\TypeCoercion\CoerceString;
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
            ConfigKey::LOG_PAYLOAD_CONTENT => new CoerceBoolean(),
            ConfigKey::ERRORS_ENABLED => new CoerceBoolean(),
            ConfigKey::CORE_AGENT_DOWNLOAD_ENABLED => new CoerceBoolean(),
            ConfigKey::CORE_AGENT_LAUNCH_ENABLED => new CoerceBoolean(),
            ConfigKey::IGNORED_ENDPOINTS => new CoerceJson(),
            ConfigKey::IGNORED_JOBS => new CoerceJson(),
            ConfigKey::DISABLED_INSTRUMENTS => new CoerceJson(),
            ConfigKey::URI_FILTERED_PARAMETERS => new CoerceJson(),
            ConfigKey::ERRORS_IGNORED_EXCEPTIONS => new CoerceJson(),
            ConfigKey::ERRORS_FILTERED_PARAMETERS => new CoerceJson(),
            ConfigKey::CORE_AGENT_PERMISSIONS => new CoerceInt(),
            ConfigKey::ERRORS_BATCH_SIZE => new CoerceInt(),
            ConfigKey::API_VERSION => new CoerceString(),
            ConfigKey::CORE_AGENT_DIRECTORY => new CoerceString(),
            ConfigKey::CORE_AGENT_VERSION => new CoerceString(),
            ConfigKey::CORE_AGENT_DOWNLOAD_URL => new CoerceString(),
            ConfigKey::LOG_LEVEL => new CoerceString(),
            ConfigKey::ERRORS_HOST => new CoerceString(),
            ConfigKey::URI_REPORTING => new CoerceString(),
            ConfigKey::CORE_AGENT_SOCKET_PATH => new CoerceString(),
            ConfigKey::CORE_AGENT_FULL_NAME => new CoerceString(),
            ConfigKey::CORE_AGENT_TRIPLE => new CoerceString(),
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
     * @param K $key
     *
     * @return bool|mixed[]|int|string|null
     * @psalm-return (
     *   K is ConfigKey::MONITORING_ENABLED|ConfigKey::LOG_PAYLOAD_CONTENT|ConfigKey::ERRORS_ENABLED|ConfigKey::CORE_AGENT_DOWNLOAD_ENABLED|ConfigKey::CORE_AGENT_LAUNCH_ENABLED ? bool
     *   : K is ConfigKey::IGNORED_ENDPOINTS|ConfigKey::IGNORED_JOBS|ConfigKey::DISABLED_INSTRUMENTS|ConfigKey::URI_FILTERED_PARAMETERS|ConfigKey::ERRORS_IGNORED_EXCEPTIONS|ConfigKey::ERRORS_FILTERED_PARAMETERS ? array|null
     *   : K is ConfigKey::CORE_AGENT_PERMISSIONS|ConfigKey::ERRORS_BATCH_SIZE ? int
     *   : K is ConfigKey::API_VERSION|ConfigKey::CORE_AGENT_DIRECTORY|ConfigKey::CORE_AGENT_VERSION|ConfigKey::CORE_AGENT_DOWNLOAD_URL|ConfigKey::LOG_LEVEL|ConfigKey::ERRORS_HOST|ConfigKey::URI_REPORTING|ConfigKey::CORE_AGENT_SOCKET_PATH|ConfigKey::CORE_AGENT_FULL_NAME|ConfigKey::CORE_AGENT_TRIPLE ? string
     *   : string|null
     * )
     *
     * @template K as string
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
