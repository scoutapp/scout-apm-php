<?php

declare(strict_types=1);

namespace Scoutapm\Helper;

use function memory_get_usage;

final class MemoryUsage
{
    private const BYTES_IN_A_MB = 1000000;

    /** @var int */
    private $bytesUsed;

    private function __construct()
    {
        $this->bytesUsed = memory_get_usage(false);
    }

    public static function record(): self
    {
        return new self();
    }

    public function usedDifferenceInMegabytes(MemoryUsage $comparedTo): float
    {
        return ($this->bytesUsed - $comparedTo->bytesUsed) / self::BYTES_IN_A_MB;
    }
}
