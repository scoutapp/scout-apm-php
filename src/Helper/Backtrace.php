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

/**
 * @internal
 *
 * @psalm-type PhpStackFrame = array{file: string, line: int, function: string, class: string, type: string}
 * @psalm-type ScoutStackFrame = array{file: string, line: int, function: string}
 */
final class Backtrace
{
    /**
     * @return array<int, array<string, string|int>>
     *
     * @psalm-return list<ScoutStackFrame>
     */
    public static function capture() : array
    {
        $capturedStack = array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 1);

        $formattedStack = [];
        foreach ($capturedStack as $frame) {
            if (! isset($frame['file'], $frame['line'], $frame['function'])) {
                continue;
            }

            /** @psalm-var PhpStackFrame $frame */
            $formattedStack[] = self::reformatStackFrame($frame);
        }

        return self::filterScoutRelatedFramesFromTopOfStack($formattedStack);
    }

    /**
     * @param array<string, string|int> $frame
     *
     * @return array<string, string|int>
     *
     * @psalm-param PhpStackFrame $frame
     * @psalm-return ScoutStackFrame
     */
    private static function reformatStackFrame(array $frame) : array
    {
        return [
            'file' => $frame['file'],
            'line' => $frame['line'],
            'function' => self::formatFunctionNameFromFrame($frame),
        ];
    }

    /**
     * @param array<string, string|int> $frame
     *
     * @psalm-param PhpStackFrame $frame
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
     * @psalm-param ScoutStackFrame $frame
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
     * @psalm-param list<ScoutStackFrame> $formattedStack
     * @psalm-return list<ScoutStackFrame>
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
