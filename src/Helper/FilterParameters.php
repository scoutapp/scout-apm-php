<?php

declare(strict_types=1);

namespace Scoutapm\Helper;

use function array_filter;
use function in_array;

use const ARRAY_FILTER_USE_KEY;

/** @internal */
abstract class FilterParameters
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
}
