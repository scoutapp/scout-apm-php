<?php

declare(strict_types=1);

namespace Scoutapm\Config;

use function strpos;

/** @internal */
final class IgnoredEndpoints
{
    /** @var array|string[] */
    private $ignoredPaths;

    /** @param string[]|array<int, string> $ignoredPaths */
    public function __construct(array $ignoredPaths)
    {
        $this->ignoredPaths = $ignoredPaths;
    }

    public function ignored(string $url): bool
    {
        foreach ($this->ignoredPaths as $ignore) {
            if (strpos($url, $ignore) === 0) {
                return true;
            }
        }

        // None Matched
        return false;
    }
}
