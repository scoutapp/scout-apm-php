<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Helper;

use PHPUnit\Framework\TestCase;
use Scoutapm\Helper\Backtrace;

use function array_keys;
use function json_encode;

/** @covers \Scoutapm\Helper\Backtrace */
final class BacktraceTest extends TestCase
{
    public function testCapturingBacktrace(): void
    {
        $backtrace = Backtrace::capture();

        // In test environment, the stack frame size will be 9 or 10, depending on how the test runner is run...
        self::assertGreaterThanOrEqual(9, $backtrace);

        foreach ($backtrace as $frame) {
            self::assertEquals(['file', 'line', 'function'], array_keys($frame));
        }
    }

    public function testCapturingBacktraceFiltersOutVendor(): void
    {
        $backtrace = Backtrace::captureWithoutVendor(0);
        // Since all Scoutapm stuff is already filtered out AND we're filtering vendor, this stack trace is actually
        // empty. This wouldn't happen if we're installed as a library, so is just a quirk of running this test
        self::assertCount(0, $backtrace, 'Expected empty backtrace, actual value: ' . json_encode($backtrace));
    }
}
