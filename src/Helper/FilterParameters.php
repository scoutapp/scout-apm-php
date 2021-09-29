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
use function is_array;
use function is_object;
use function is_scalar;
use function sprintf;
use function strtolower;

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
        $lowercasedParameterKeysToBeFiltered = array_map('strtolower', $parameterKeysToBeFiltered);

        return array_filter(
            $parameters,
            static function (string $key) use ($lowercasedParameterKeysToBeFiltered): bool {
                return ! in_array(strtolower($key), $lowercasedParameterKeysToBeFiltered, true);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @return string[]
     *
     * @psalm-pure
     * @psalm-template T as int
     * @psalm-param list<string> $parameterKeysToBeFiltered
     * @psalm-param array<array-key, mixed> $parameters
     * @psalm-param T $depth
     * @psalm-return (
     *      T is 1
     *      ? array<string,string>
     *      : (
     *          T is 2
     *          ? array<string,string|array<string,string>>
     *          : (
     *              T is 3
     *              ? array<string,string|array<string,string|array<string,string>>>
     *              : (
     *                  T is 4
     *                  ? array<string,string|array<string,string|array<string,string|array<string,string>>>>
     *                  : array
     *              )
     *          )
     *      )
     * )
     */
    public static function flattenedForUriReportingConfiguration(array $parameterKeysToBeFiltered, array $parameters, int $depth = 1): array
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
                static function ($value) use ($parameterKeysToBeFiltered, $depth) {
                    if (is_array($value) && $depth > 1) {
                        return self::flattenedForUriReportingConfiguration($parameterKeysToBeFiltered, $value, (int) ($depth) - 1);
                    }

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
