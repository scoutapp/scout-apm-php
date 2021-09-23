<?php

declare(strict_types=1);

namespace Scoutapm\Helper;

use function array_combine;
use function array_filter;
use function array_keys;
use function array_map;
use function get_class;
use function gettype;
use function in_array;
use function is_object;
use function is_scalar;
use function sprintf;

use const ARRAY_FILTER_USE_KEY;

/** @internal */
final class FilterParameters
{
    /**
     * @return array<array-key, mixed>
     *
     * @psalm-pure
     * @psalm-param list<string> $parameterKeysToBeFiltered
     * @psalm-param array<array-key, mixed> $parameters
     */
    public static function forUriReportingConfiguration(array $parameterKeysToBeFiltered, array $parameters): array
    {
        return array_filter(
            $parameters,
            static function (string $key) use ($parameterKeysToBeFiltered): bool {
                return ! in_array($key, $parameterKeysToBeFiltered, true);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @return array<string, string>
     *
     * @psalm-pure
     * @psalm-param list<string> $parameterKeysToBeFiltered
     * @psalm-param array<array-key, mixed> $parameters
     */
    public static function flattenedForUriReportingConfiguration(array $parameterKeysToBeFiltered, array $parameters): array
    {
        $filteredParameters = self::forUriReportingConfiguration($parameterKeysToBeFiltered, $parameters);

        return array_combine(
            array_map(
                static function ($key) {
                    return (string) $key;
                },
                array_keys($filteredParameters)
            ),
            array_map(
                static function ($value): string {
                    if (is_scalar($value)) {
                        return (string) $value;
                    }

                    return is_object($value) ? sprintf('object(%s)', get_class($value)) : gettype($value);
                },
                $filteredParameters
            )
        );
    }
}
