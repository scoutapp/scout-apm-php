<?php

declare(strict_types=1);

namespace Scoutapm\Helper;

use function array_combine;
use function array_keys;
use function array_map;

final class Superglobals
{
    /**
     * @param array<array-key, mixed> $mixedArray
     *
     * @return array<string, string>
     */
    private static function convertKeysAndValuesToStrings(array $mixedArray): array
    {
        return array_combine(
            array_map(
                static function ($key): string {
                    return (string) $key;
                },
                array_keys($mixedArray)
            ),
            array_map(
                static function ($value): string {
                    return (string) $value;
                },
                $mixedArray
            )
        );
    }

    /** @return array<array-key, mixed> */
    public static function session(): array
    {
        return $_SESSION ?? [];
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
