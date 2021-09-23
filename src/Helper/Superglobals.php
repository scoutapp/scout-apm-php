<?php

declare(strict_types=1);

namespace Scoutapm\Helper;

final class Superglobals
{
    /** @return array<array-key, mixed> */
    public static function session(): array
    {
        return $_SESSION ?? [];
    }

    /** @return array<array-key, mixed> */
    public static function env(): array
    {
        return $_ENV;
    }
}
