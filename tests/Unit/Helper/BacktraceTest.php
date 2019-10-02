<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Helper;

use PHPUnit\Framework\TestCase;
use Scoutapm\Helper\Backtrace;
use function array_keys;

/** @covers \Scoutapm\Helper\Backtrace */
final class BacktraceTest extends TestCase
{
    public function testCapturingBacktrace() : void
    {
        $stack = Backtrace::capture();

        foreach ($stack as $frame) {
            self::assertEquals(['file', 'line', 'function'], array_keys($frame));
        }
    }
}
