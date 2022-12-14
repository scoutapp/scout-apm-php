<?php

declare(strict_types=1);

namespace Scoutapm\Helper\Superglobals;

use Throwable;
use Webmozart\Assert\Assert;

use function array_combine;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_values;
use function is_object;
use function is_scalar;
use function method_exists;

/** @internal This is not covered by BC promise */
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
    /** @var list<string> */
    private $argv;

    /**
     * @internal This is not covered by BC promise
     *
     * @param array<array-key, mixed> $session
     * @param array<array-key, mixed> $request
     * @param array<string, string>   $env
     * @param array<string, string>   $server
     * @param list<string>            $argv
     */
    public function __construct(array $session, array $request, array $env, array $server, array $argv)
    {
        $this->session = $session;
        $this->request = $request;
        $this->env     = $env;
        $this->server  = $server;
        $this->argv    = $argv;
    }

    /** @return list<string> */
    private static function typeSafeArgvFromGlobals(): array
    {
        if (! array_key_exists('argv', $GLOBALS)) {
            return [];
        }

        $argv = $GLOBALS['argv'];

        try {
            Assert::isArray($argv);
            Assert::allString($argv);

            return array_values($argv);
        } catch (Throwable $anything) {
            return [];
        }
    }

    /** @internal This is not covered by BC promise */
    public static function fromGlobalState(): self
    {
        return new self(
            $_SESSION ?? [],
            $_REQUEST,
            self::convertKeysAndValuesToStrings($_ENV),
            self::convertKeysAndValuesToStrings($_SERVER),
            self::typeSafeArgvFromGlobals()
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

    /** @return list<string> */
    public function argv(): array
    {
        return $this->argv;
    }
}
