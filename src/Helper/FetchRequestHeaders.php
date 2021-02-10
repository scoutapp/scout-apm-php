<?php

declare(strict_types=1);

namespace Scoutapm\Helper;

use function array_combine;
use function array_filter;
use function array_keys;
use function array_map;
use function is_string;
use function str_replace;
use function strpos;
use function strtolower;
use function substr;
use function ucwords;

use const ARRAY_FILTER_USE_BOTH;

abstract class FetchRequestHeaders
{
    /** @return array<string, string> */
    public static function fromServerGlobal(): array
    {
        return self::fromArray($_SERVER);
    }

    /**
     * @param array<int|string, string> $server
     *
     * @return array<string, string>
     */
    private static function fromArray(array $server): array
    {
        $qualifyingServerKeys = self::onlyQualifyingServerItems($server);

        return array_combine(
            array_map(
                static function (string $key): string {
                    return ucwords(str_replace('_', '-', strtolower(substr($key, 5))), '-');
                },
                array_keys($qualifyingServerKeys)
            ),
            $qualifyingServerKeys
        );
    }

    /**
     * @param array<int|string, string> $server
     *
     * @return array<string, string>
     *
     * @psalm-suppress InvalidReturnType
     */
    private static function onlyQualifyingServerItems(array $server): array
    {
        /** @psalm-suppress InvalidReturnStatement */
        return array_filter(
            $server,
            /**
             * @param mixed $value
             * @param mixed $key
             */
            static function ($value, $key): bool {
                return is_string($key)
                    && $value !== ''
                    && strpos($key, 'HTTP_') === 0;
            },
            ARRAY_FILTER_USE_BOTH
        );
    }
}
