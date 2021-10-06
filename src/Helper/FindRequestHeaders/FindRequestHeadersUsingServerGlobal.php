<?php

declare(strict_types=1);

namespace Scoutapm\Helper\FindRequestHeaders;

use Scoutapm\Helper\Superglobals\Superglobals;

use function array_combine;
use function array_filter;
use function array_keys;
use function array_map;
use function in_array;
use function is_string;
use function str_replace;
use function strtolower;
use function substr;
use function ucwords;

use const ARRAY_FILTER_USE_BOTH;

/** @internal */
final class FindRequestHeadersUsingServerGlobal implements FindRequestHeaders
{
    /** @var Superglobals */
    private $superglobals;

    public function __construct(Superglobals $superglobals)
    {
        $this->superglobals = $superglobals;
    }

    /**
     * @internal
     *
     * @return array<string, string>
     */
    public function __invoke(): array
    {
        $qualifyingServerKeys = $this->onlyQualifyingServerItems($this->superglobals->server());

        return array_combine(
            array_map(
                static function (string $key): string {
                    if (in_array(strtolower(substr($key, 0, 5)), ['http_', 'http-'], true)) {
                        $key = substr($key, 5);
                    }

                    return ucwords(str_replace('_', '-', strtolower($key)), '-');
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
    private function onlyQualifyingServerItems(array $server): array
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
                    && $value !== '';
            },
            ARRAY_FILTER_USE_BOTH
        );
    }
}
