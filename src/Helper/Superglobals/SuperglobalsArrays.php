<?php

declare(strict_types=1);

namespace Scoutapm\Helper\Superglobals;

use function array_combine;
use function array_filter;
use function array_keys;
use function array_map;
use function is_object;
use function is_scalar;
use function method_exists;

/**
 * @internal This is not covered by BC promise
 */
final class SuperglobalsArrays implements Superglobals
{
    /** @var array<array-key, mixed> */
    private $session;
    /** @var array<array-key, mixed> */
    private $request;
    /** @var array<string, string> */
    private $env;
    /** @var array<string, string> */
    private $server;

    /**
     * @param array<array-key, mixed> $session
     * @param array<array-key, mixed> $request
     * @param array<string, string>   $env
     * @param array<string, string>   $server
     */
    public function __construct(array $session, array $request, array $env, array $server)
    {
        $this->session = $session;
        $this->request = $request;
        $this->env     = $env;
        $this->server  = $server;
    }

    public static function fromGlobalState(): self
    {
        return new self(
            $_SESSION ?? [],
            $_REQUEST,
            self::convertKeysAndValuesToStrings($_ENV),
            self::convertKeysAndValuesToStrings($_SERVER)
        );
    }

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
    public function session(): array
    {
        return $this->session;
    }

    /** @return array<array-key, mixed> */
    public function request(): array
    {
        return $this->request;
    }

    /** @return array<string, string> */
    public function env(): array
    {
        return $this->env;
    }

    /** @return array<string, string> */
    public function server(): array
    {
        return $this->server;
    }
}
