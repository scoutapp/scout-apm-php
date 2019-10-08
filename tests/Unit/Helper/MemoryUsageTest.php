<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Helper;

use PHPUnit\Framework\TestCase;
use Scoutapm\Helper\MemoryUsage;

/** @covers \Scoutapm\Helper\MemoryUsage */
final class MemoryUsageTest extends TestCase
{
    public function testMemoryUsageCanBeRecorded() : void
    {
        $usage = MemoryUsage::record()->jsonSerialize();

        self::assertArrayHasKey('allocated', $usage);
        self::assertArrayHasKey('used', $usage);
        self::assertArrayHasKey('peak_allocated', $usage);
        self::assertArrayHasKey('peak_used', $usage);

        self::assertIsInt($usage['allocated']);
        self::assertIsInt($usage['used']);
        self::assertIsInt($usage['peak_allocated']);
        self::assertIsInt($usage['peak_used']);

        self::assertGreaterThan(0, $usage['allocated']);
        self::assertGreaterThan(0, $usage['used']);
        self::assertGreaterThan(0, $usage['peak_allocated']);
        self::assertGreaterThan(0, $usage['peak_used']);
    }
}
