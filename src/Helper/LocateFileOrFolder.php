<?php

declare(strict_types=1);

namespace Scoutapm\Helper;

use function dirname;
use function file_exists;
use function is_readable;
use function realpath;

/**
 * @internal Not covered by BC promise
 *
 * @todo define an interface here
 */
class LocateFileOrFolder
{
    /**
     * Try to locate a file or folder in any parent directory (upwards of this library itself)
     *
     * For example, `composer.json` is located:
     *  - /home/user/workspace/my-app/composer.json
     *
     * When we use (new LocateFileOrFolder())->__invoke('composer.json') with default settings, we try, in order:
     *  - /home/user/workspace/my-app/vendor/scoutapp/scout-apm-php/src/Helper/composer.json (Fail, but is skipped by default)
     *  - /home/user/workspace/my-app/vendor/scoutapp/scout-apm-php/src/composer.json (Fail, but is skipped by default)
     *  - /home/user/workspace/my-app/vendor/scoutapp/scout-apm-php/composer.json (Fail, but is skipped by default)
     *  - /home/user/workspace/my-app/vendor/scoutapp/composer.json (Fail, doesn't exist)
     *  - /home/user/workspace/my-app/vendor/composer.json (Fail, doesn't exist)
     *  - /home/user/workspace/my-app/composer.json (Success - will return `/home/user/workspace/my-app`)
     *
     * Note: when developing on the library, this will usually return `null`, since the paths (in order) are:
     *  - /home/user/workspace/scout-apm-php/src/Helper/composer.json (Fail, skipped by default)
     *  - /home/user/workspace/scout-apm-php/src/composer.json (Fail, skipped by default)
     *  - /home/user/workspace/scout-apm-php/composer.json (Success, but skipped by default)
     *  - /home/user/workspace/composer.json (Fail, doesn't exist)
     *  - /home/user/composer.json (Fail, doesn't exist)
     *  - /home/composer.json (Fail, doesn't exist)
     *  - /composer.json (Fail, doesn't exist, reached "root", so return `null`)
     */
    public function __invoke(string $fileOrFolder, int $skipLevels = 3): ?string
    {
        $dir = __DIR__;

        // Starting 3 levels up will avoid finding scout-apm-php's own contents, speeding up the process
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
