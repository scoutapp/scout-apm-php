<?php

declare(strict_types=1);

namespace Scoutapm\Helper;

use function array_combine;
use function array_filter;
use function array_keys;
use function array_map;
use function is_object;
use function is_scalar;
use function method_exists;

final class Superglobals
{
    /**
     * @param array<array-key, mixed> $mixedArray
     *
     * @return array<string, string>
     */
    private static function convertKeysAndValuesToStrings(array $mixedArray): array
    {
        $stringableArray = array_filter(
            $mixedArray,
            /** @param mixed $value */
            static function ($value): bool {
                return is_scalar($value) || $value === null || (is_object($value) && method_exists($value, '__toString'));
            }
        );

        return array_combine(
            array_map(
                static function ($key): string {
                    return (string) $key;
                },
                array_keys($stringableArray)
            ),
            array_map(
                static function ($value): string {
                    return (string) $value;
                },
                $stringableArray
            )
        );
    }

    /** @return array<array-key, mixed> */
    public static function session(): array
    {
        return $_SESSION ?? [];
    }

    /** @return array<array-key, mixed> */
    public static function request(): array
    {
        return $_REQUEST;
    }

    /** @return array<string, string> */
    public static function env(): array
    {
        return self::convertKeysAndValuesToStrings($_ENV);
    }

    /** @return array<string, string> */
    public static function server(): array
    {
        return self::convertKeysAndValuesToStrings($_SERVER);
    }
}
