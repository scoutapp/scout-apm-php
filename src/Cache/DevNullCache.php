<?php

declare(strict_types=1);

namespace Scoutapm\Cache;

use Composer\InstalledVersions;

$simpleCacheVersion = InstalledVersions::getPrettyVersion('psr/simple-cache');
if (version_compare($simpleCacheVersion, '2.0.0', '<')) {
    /** @internal */
    final class DevNullCache extends DevNullCacheSimpleCache1
    {
    }

    return;
}

/** @internal */
final class DevNullCache extends DevNullCacheSimpleCache2And3
{
}
