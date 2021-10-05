<?php

declare(strict_types=1);

namespace Scoutapm\Helper\Superglobals;

/**
 * @internal This is not covered by BC promise
 */
interface Superglobals
{
    /** @return array<array-key, mixed> */
    public function session(): array;

    /** @return array<array-key, mixed> */
    public function request(): array;

    /** @return array<string, string> */
    public function env(): array;

    /** @return array<string, string> */
    public function server(): array;
}
