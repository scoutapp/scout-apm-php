<?php

declare(strict_types=1);

namespace Scoutapm\Helper;

use JsonSerializable;
use function memory_get_peak_usage;
use function memory_get_usage;

final class MemoryUsage implements JsonSerializable
{
    /** @var int */
    private $bytesAllocated;

    /** @var int */
    private $bytesUsed;

    /** @var int */
    private $peakBytesAllocated;

    /** @var int */
    private $peakBytesUsed;

    private function __construct()
    {
        $this->bytesAllocated     = memory_get_usage(true);
        $this->bytesUsed          = memory_get_usage(false);
        $this->peakBytesAllocated = memory_get_peak_usage(true);
        $this->peakBytesUsed      = memory_get_peak_usage(false);
    }

    public static function record() : self
    {
        return new self();
    }

    /**
     * @return int[]|array<string, int>
     *
     * @psalm-return array{allocated: int, used: int, peak_allocated: int, peak_used: int}
     */
    public function jsonSerialize() : array
    {
        return [
            'allocated' => $this->bytesAllocated,
            'used' => $this->bytesUsed,
            'peak_allocated' => $this->peakBytesAllocated,
            'peak_used' => $this->peakBytesUsed,
        ];
    }
}
