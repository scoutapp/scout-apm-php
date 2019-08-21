<?php

declare(strict_types=1);

namespace Scoutapm\Helper;

use function debug_backtrace;

/** @internal */
final class Backtrace
{
    /** @return array<int, array<string, string>> */
    public static function capture() : array
    {
        $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        $formatted_stack = [];
        foreach ($stack as $frame) {
            if (!isset($frame['file'], $frame['line'], $frame['function'])) {
                continue;
            }

            $formatted_stack[] = [
                'file' => $frame['file'],
                'line' => $frame['line'],
                'function' => $frame['function'],
            ];
        }

        return $formatted_stack;
    }
}
