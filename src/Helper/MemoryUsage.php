<?php

declare(strict_types=1);

namespace Scoutapm\Helper;

use function memory_get_usage;

final class MemoryUsage
{
    /** @var int */
    private $bytesUsed;

    private function __construct()
    {
        $this->bytesUsed = memory_get_usage(false);
    }

    public static function record() : self
    {
        return new self();
    }

    public function usedDifference(MemoryUsage $comparedTo) : int
    {
        return $this->bytesUsed - $comparedTo->bytesUsed;
    }
}
