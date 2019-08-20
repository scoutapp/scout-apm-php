<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events;

use PHPUnit\Framework\TestCase;
use Scoutapm\Agent;
use Scoutapm\Events\Span;

/**
 * Test Case for @see \Scoutapm\Events\Span
 */
final class SpanTest extends TestCase
{
    public function testCanBeInitialized() : void
    {
        $span = new Span(new Agent(), 'name', 'reqid');
        $this->assertNotNull($span);
    }

    public function testCanBeStopped() : void
    {
        $span = new Span(new Agent(), '', 'reqid');
        $span->stop();
        $this->assertNotNull($span);
    }

    public function testJsonSerializes() : void
    {
        $span = new Span(new Agent(), '', 'reqid');
        $span->tag('Foo', 'Bar');
        $span->stop();

        $serialized = $span->jsonSerialize();

        $this->assertIsArray($serialized);
        $this->assertArrayHasKey('StartSpan', $serialized[0]);
        $this->assertArrayHasKey('TagSpan', $serialized[1]);
        $this->assertArrayHasKey('StopSpan', $serialized[2]);
    }

    public function testSpanNameOverride() : void
    {
        $span = new Span(new Agent(), 'original', 'reqid');
        $this->assertEquals('original', $span->getName());

        $span->updateName('new');
        $this->assertEquals('new', $span->getName());
    }
}
