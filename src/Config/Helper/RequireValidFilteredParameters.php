<?php

declare(strict_types=1);

namespace Scoutapm\Config\Helper;

use InvalidArgumentException;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;

use function array_values;
use function get_class;
use function gettype;
use function is_array;
use function is_object;
use function is_string;
use function sprintf;

/** @internal */
final class RequireValidFilteredParameters
{
    /**
     * @psalm-param ConfigKey::URI_FILTERED_PARAMETERS|ConfigKey::ERRORS_FILTERED_PARAMETERS $filteredParametersConfigKey
     *
     * @psalm-return list<string>
     */
    private static function fromConfigForGivenKey(Config $config, string $filteredParametersConfigKey): array
    {
        /** @var mixed $uriFilteredParameters */
        $uriFilteredParameters = $config->get($filteredParametersConfigKey);

        /** @var list<string> $defaultFilteredParameters */
        $defaultFilteredParameters = (new Config\Source\DefaultSource())->get($filteredParametersConfigKey);

        if (! is_array($uriFilteredParameters)) {
            return $defaultFilteredParameters;
        }

        foreach ($uriFilteredParameters as $filteredParameter) {
            if (! is_string($filteredParameter)) {
                throw new InvalidArgumentException(sprintf(
                    'Parameter value for configuration "%s" was invalid - expected string, found %s',
                    $filteredParametersConfigKey,
                    is_object($filteredParameter) ? get_class($filteredParameter) : gettype($filteredParameter)
                ));
            }
        }

        /** @psalm-var array<array-key, string> $uriFilteredParameters */
        return array_values($uriFilteredParameters);
    }

    /** @psalm-return list<string> */
    public static function fromConfigForErrors(Config $config): array
    {
        return self::fromConfigForGivenKey($config, ConfigKey::ERRORS_FILTERED_PARAMETERS);
    }

    /** @psalm-return list<string> */
    public static function fromConfigForUris(Config $config): array
    {
        return self::fromConfigForGivenKey($config, ConfigKey::URI_FILTERED_PARAMETERS);
    }
}
