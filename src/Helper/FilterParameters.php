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
     * @psalm-param list<string> $parameterKeysToBeFiltered
     * @psalm-param array<array-key, mixed> $parameters
     *
     * @return array<array-key, mixed>
     *
     * @psalm-pure
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
     * @psalm-param list<string> $parameterKeysToBeFiltered
     * @psalm-param array<array-key, mixed> $parameters
     * @psalm-param T $depth
     *
     * @return string[]
     * @psalm-return (
     *      T is 0|1
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
     *
     * @psalm-pure
     * @psalm-template T as positive-int|0
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
                        /** @psalm-suppress ArgumentTypeCoercion https://github.com/vimeo/psalm/issues/7235 */
                        return self::flattenedForUriReportingConfiguration($parameterKeysToBeFiltered, $value, $depth - 1);
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
