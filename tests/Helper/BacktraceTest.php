<?php
namespace Scoutapm\Tests\Helper;

use \PHPUnit\Framework\TestCase;
use \Scoutapm\Helper\Backtrace;

/**
 * Test Case for @see \Scoutapm\Helper\Backtrace
 */
final class BacktraceTest extends TestCase
{
    public function testCapturingBacktrace()
    {
        $stack = Backtrace::capture();
        $this->assertNotNull($stack);
        foreach ($stack as $frame) {
            $this->assertArrayHasKey('file', $frame);
            $this->assertArrayHasKey('line', $frame);
            $this->assertArrayHasKey('function', $frame);
        }
    }
}
