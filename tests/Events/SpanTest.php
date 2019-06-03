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
        $span = new Span(new Agent(), 'name', 'reqid');
        $this->assertNotNull($span);
    }

    public function testCanBeStopped()
    {
        $span = new Span(new Agent(), '', 'reqid');
        $span->stop();
        $this->assertNotNull($span);
    }

    public function testJsonSerializes()
    {
        $span = new Span(new Agent(), '', 'reqid');
        $span->tag("Foo", "Bar");
        $span->stop();

        $serialized = $span->jsonSerialize();

        $this->assertInternalType('array', $serialized);
        $this->assertArrayHasKey("StartSpan", $serialized[0]);
        $this->assertArrayHasKey("TagSpan", $serialized[1]);
        $this->assertArrayHasKey("StopSpan", $serialized[2]);
    }
}
