<?php

declare(strict_types=1);

namespace Scoutapm\Helper;

use function array_push;
use function debug_backtrace;

/** @internal */
final class Backtrace
{
    /** @return array<int, array<string, string>> */
    public static function capture() : array
    {
        $stack = debug_backtrace();

        $formatted_stack = [];
        foreach ($stack as $frame) {
            if (! isset($frame['file']) || ! isset($frame['line']) || ! isset($frame['function'])) {
                continue;
            }

            array_push($formatted_stack, ['file' => $frame['file'], 'line' => $frame['line'], 'function' => $frame['function']]);
        }

        return $formatted_stack;
    }
}
