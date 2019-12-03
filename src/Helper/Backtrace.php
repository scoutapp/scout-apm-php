<?php

declare(strict_types=1);

namespace Scoutapm\Helper;

use const DEBUG_BACKTRACE_IGNORE_ARGS;
use function array_filter;
use function array_key_exists;
use function array_slice;
use function array_values;
use function debug_backtrace;
use function strpos;

/** @internal */
final class Backtrace
{
    /**
     * @return array<int, array<string, string|int>>
     *
     * @psalm-return array<int, array{file: string, line: int, function: string}>
     */
    public static function capture() : array
    {
        $capturedStack = array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 1);

        $formattedStack = [];
        foreach ($capturedStack as $frame) {
            if (! isset($frame['file'], $frame['line'], $frame['function'])) {
                continue;
            }

            /** @psalm-var array{file: string, line: int, function: string, class: string, type: string} $frame */
            $formattedStack[] = [
                'file' => $frame['file'],
                'line' => $frame['line'],
                'function' => self::formatFunctionNameFromFrame($frame),
            ];
        }

        return self::filterScoutRelatedFramesFromTopOfStack($formattedStack);
    }

    /**
     * @param array<string, string|int> $frame
     *
     * @psalm-param array{file: string, line: int, function: string, class: string, type: string} $frame
     */
    private static function formatFunctionNameFromFrame(array $frame) : string
    {
        if (! array_key_exists('class', $frame) || ! array_key_exists('type', $frame)) {
            return $frame['function'];
        }

        return $frame['class'] . $frame['type'] . $frame['function'];
    }

    /**
     * @param array<string, string|int> $frame
     *
     * @psalm-param array{file: string, line: int, function: string} $frame
     */
    private static function isScoutRelated(array $frame) : bool
    {
        return strpos($frame['function'], 'Scoutapm') === 0;
    }

    /**
     * @param array<int, array<string, string|int>> $formattedStack
     *
     * @return array<int, array<string, string|int>>
     *
     * @psalm-param array<int, array{file: string, line: int, function: string}> $formattedStack
     * @psalm-return array<int, array{file: string, line: int, function: string}>
     */
    private static function filterScoutRelatedFramesFromTopOfStack(array $formattedStack) : array
    {
        $stillInsideScout = true;

        return array_values(array_filter(
            $formattedStack,
            static function (array $frame) use (&$stillInsideScout) : bool {
                if (! $stillInsideScout) {
                    return true;
                }

                if (self::isScoutRelated($frame)) {
                    return false;
                }

                $stillInsideScout = false;

                return true;
            }
        ));
    }
}
