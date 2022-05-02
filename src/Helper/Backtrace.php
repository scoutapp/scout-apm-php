<?php

declare(strict_types=1);

namespace Scoutapm\Helper;

use Scoutapm\Helper\LocateFileOrFolder\LocateFileOrFolder;
use Scoutapm\Helper\LocateFileOrFolder\LocateFileOrFolderUsingFilesystem;

use function array_filter;
use function array_key_exists;
use function array_slice;
use function array_values;
use function debug_backtrace;
use function file_get_contents;
use function is_array;
use function json_decode;
use function str_replace;
use function strpos;

use const DEBUG_BACKTRACE_IGNORE_ARGS;
use const DIRECTORY_SEPARATOR;

/**
 * @internal
 *
 * @psalm-type PhpStackFrame = array{file: string, line: int, function: string, class: string, type: string}
 * @psalm-type ScoutStackFrame = array{file: string, line: int, function: string}
 */
final class Backtrace
{
    /**
     * Returns a simplified stack trace with just file/line/function keys for each stack frame. We also filter out any
     * classes that belong in the `Scoutapm` namespace to avoid including our own library's contents which won't be
     * relevant to customer monitoring.
     *
     * @return array<int, array<string, string|int>>
     * @psalm-return list<ScoutStackFrame>
     */
    public static function capture(): array
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
     * Same as `capture()` but we filter out everything under the `vendor/` directory. Note that for this to operate as
     * we expect, a `composer.json` must exist, and also the `vendor-dir` configuration option must NOT be used. The
     * stack trace is returned as-is (i.e. `vendor/` stack frames will NOT be filtered out) if either of these
     * conditions are not met.
     *
     * @return array<int, array<string, string|int>>
     * @psalm-return list<ScoutStackFrame>
     */
    public static function captureWithoutVendor(int $skipPathLevelsWhenLocatingComposerJson = LocateFileOrFolder::SKIP_LEVELS_DEFAULT): array
    {
        return self::filterVendorFramesFromStack(self::capture(), $skipPathLevelsWhenLocatingComposerJson);
    }

    /**
     * @param array<string, string|int> $frame
     * @psalm-param PhpStackFrame $frame
     *
     * @return array<string, string|int>
     * @psalm-return ScoutStackFrame
     */
    private static function reformatStackFrame(array $frame): array
    {
        /**
         * The phpvfscomposer:// stream wrapper was added to fix a bug in Composer 2.2, which affects all PHP 7.*
         * versions we support. Simple fix is to remove the protocol from the filename, as it is an edge case. This
         * str_replace can be dropped if/when we drop PHP 7.* support.
         *
         * @link https://github.com/composer/composer/issues/10387
         */
        return [
            'file' => str_replace('phpvfscomposer://', '', $frame['file']),
            'line' => $frame['line'],
            'function' => self::formatFunctionNameFromFrame($frame),
        ];
    }

    /**
     * @param array<string, string|int> $frame
     * @psalm-param PhpStackFrame $frame
     */
    private static function formatFunctionNameFromFrame(array $frame): string
    {
        if (! array_key_exists('class', $frame) || ! array_key_exists('type', $frame)) {
            return $frame['function'];
        }

        return $frame['class'] . $frame['type'] . $frame['function'];
    }

    /**
     * @param array<string, string|int> $frame
     * @psalm-param ScoutStackFrame $frame
     */
    private static function isScoutRelated(array $frame): bool
    {
        /** @noinspection StrStartsWithCanBeUsedInspection */
        return strpos($frame['function'], 'Scoutapm') === 0;
    }

    /**
     * @param array<int, array<string, string|int>> $formattedStack
     * @psalm-param list<ScoutStackFrame> $formattedStack
     *
     * @return array<int, array<string, string|int>>
     * @psalm-return list<ScoutStackFrame>
     */
    private static function filterScoutRelatedFramesFromTopOfStack(array $formattedStack): array
    {
        $stillInsideScout = true;

        return array_values(array_filter(
            $formattedStack,
            static function (array $frame) use (&$stillInsideScout): bool {
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

    /**
     * @param array<int, array<string, string|int>> $formattedStack
     * @psalm-param list<ScoutStackFrame> $formattedStack
     *
     * @return array<int, array<string, string|int>>
     * @psalm-return list<ScoutStackFrame>
     */
    private static function filterVendorFramesFromStack(array $formattedStack, int $skipPathLevelsWhenLocatingComposerJson): array
    {
        // @todo static instantiation of service should be refactored
        $pathWhereComposerLives = (new LocateFileOrFolderUsingFilesystem())->__invoke('composer.json', $skipPathLevelsWhenLocatingComposerJson);

        // Probably not using composer, so we don't know how to find `vendor` directory anyway, so return early
        if ($pathWhereComposerLives === null) {
            return $formattedStack;
        }

        // The `vendor-dir` configuration is explicitly NOT supported, typical setups will be fine
        $composerContent = json_decode(file_get_contents($pathWhereComposerLives . '/composer.json'), true);
        if (
            is_array($composerContent)
            && array_key_exists('config', $composerContent)
            && array_key_exists('vendor-dir', $composerContent['config'])
        ) {
            return $formattedStack;
        }

        $vendorPath = $pathWhereComposerLives . DIRECTORY_SEPARATOR . 'vendor';

        return array_values(array_filter(
            $formattedStack,
            static function (array $frame) use ($vendorPath): bool {
                /** @noinspection StrStartsWithCanBeUsedInspection */
                return strpos($frame['file'], $vendorPath) !== 0;
            }
        ));
    }
}
