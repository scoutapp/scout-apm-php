<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Helper;

use PHPUnit\Framework\TestCase;
use Scoutapm\Helper\MemoryUsage;

use function str_repeat;

/** @covers \Scoutapm\Helper\MemoryUsage */
final class MemoryUsageTest extends TestCase
{
    public function testMemoryUsageCanBeRecorded(): void
    {
        $usageBefore = MemoryUsage::record();

        /** @noinspection PhpUnusedLocalVariableInspection */
        $block = str_repeat('a', 1000000);

        $usageAfter = MemoryUsage::record();

        // In reality, because a zval is larger, the allocation will be more like 1392, but as long as it's more!
        self::assertGreaterThanOrEqual(
            1,
            $usageAfter->usedDifferenceInMegabytes($usageBefore)
        );
    }
}
