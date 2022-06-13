<?php

declare(strict_types=1);

namespace Scoutapm\Helper;

use function stripos;

use const PHP_OS;

/** @internal */
final class Platform
{
    public static function isWindows(): bool
    {
        return stripos(PHP_OS, 'Win') === 0;
    }
}
