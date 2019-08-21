<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Helper;

use PHPUnit\Framework\TestCase;
use Scoutapm\Helper\Backtrace;

/** @covers \Scoutapm\Helper\Backtrace */
final class BacktraceTest extends TestCase
{
    public function testCapturingBacktrace() : void
    {
        $stack = Backtrace::capture();
        self::assertNotNull($stack);
        foreach ($stack as $frame) {
            self::assertArrayHasKey('file', $frame);
            self::assertArrayHasKey('line', $frame);
            self::assertArrayHasKey('function', $frame);
        }
    }
}
