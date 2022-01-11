<?php

declare(strict_types=1);

namespace Scoutapm\Helper\LocateFileOrFolder;

use function dirname;
use function file_exists;
use function is_readable;
use function realpath;

/** @internal Not covered by BC promise */
final class LocateFileOrFolderUsingFilesystem implements LocateFileOrFolder
{
    /**
     * @internal This is not covered by BC promise
     *
     * @inheritDoc
     */
    public function __invoke(string $fileOrFolder, int $skipLevels = LocateFileOrFolder::SKIP_LEVELS_DEFAULT): ?string
    {
        $dir = __DIR__;

        // Starting some levels up will avoid finding scout-apm-php's own contents, speeding up the process
        if ($skipLevels > 0) {
            $dir = dirname(__DIR__, $skipLevels);
        }

        $rootOrHome = '/';

        while (dirname($dir) !== $dir && $dir !== $rootOrHome) {
            $fileOrFolderAttempted = $dir . '/' . $fileOrFolder;
            if (file_exists($fileOrFolderAttempted) && is_readable($fileOrFolderAttempted)) {
                $realPath = realpath($dir);

                if ($realPath === false) {
                    return null;
                }

                return $realPath;
            }

            $dir = dirname($dir);
        }

        return null;
    }
}
