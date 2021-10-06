<?php

declare(strict_types=1);

namespace Scoutapm\Helper\LocateFileOrFolder;

/** @internal This is not covered by BC promise */
interface LocateFileOrFolder
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
     *
     * @internal This is not covered by BC promise
     */
    public function __invoke(string $fileOrFolder, int $skipLevels = 3): ?string;
}
