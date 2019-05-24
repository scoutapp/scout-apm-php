<?php
namespace Scoutapm\Events\Tests;

use \PHPUnit\Framework\TestCase;
use \Scoutapm\Agent;
use \Scoutapm\Events\Span;

/**
 * Test Case for @see \Scoutapm\Events\Span
 */
final class SpanTest extends TestCase
{
    public function testCanBeInitialized()
    {
        $span = new Span(new Agent(), '');
        $this->assertNotNull($span);
    }

    public function testCanBeStopped()
    {
        $span = new Span(new Agent(), '');
        $span->stop();
        $this->assertNotNull($span);
    }

    public function testJsonSerializes()
    {
        $span = new Span(new Agent(), '');
        $span->stop();

        $serialized = $span->jsonSerialize();
        $this->assertInternalType('array', $serialized);
    }
}
