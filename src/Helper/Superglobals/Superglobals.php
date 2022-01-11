<?php

declare(strict_types=1);

namespace Scoutapm\Helper\Superglobals;

/**
 * @internal This is not covered by BC promise
 */
interface Superglobals
{
    /**
     * @internal This is not covered by BC promise
     *
     * @return array<array-key, mixed>
     */
    public function session(): array;

    /**
     * @internal This is not covered by BC promise
     *
     * @return array<array-key, mixed>
     */
    public function request(): array;

    /**
     * @internal This is not covered by BC promise
     *
     * @return array<string, string>
     */
    public function env(): array;

    /**
     * @internal This is not covered by BC promise
     *
     * @return array<string, string>
     */
    public function server(): array;
}
