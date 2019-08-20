<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events;

use PHPUnit\Framework\TestCase;
use Scoutapm\Agent;
use Scoutapm\Events\Span;

/** @covers \Scoutapm\Events\Span */
final class SpanTest extends TestCase
{
    public function testCanBeInitialized() : void
    {
        $span = new Span(new Agent(), 'name', 'reqid');
        self::assertNotNull($span);
    }

    public function testCanBeStopped() : void
    {
        $span = new Span(new Agent(), '', 'reqid');
        $span->stop();
        self::assertNotNull($span);
    }

    public function testJsonSerializes() : void
    {
        $span = new Span(new Agent(), '', 'reqid');
        $span->tag('Foo', 'Bar');
        $span->stop();

        $serialized = $span->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('StartSpan', $serialized[0]);
        self::assertArrayHasKey('TagSpan', $serialized[1]);
        self::assertArrayHasKey('StopSpan', $serialized[2]);
    }

    public function testSpanNameOverride() : void
    {
        $span = new Span(new Agent(), 'original', 'reqid');
        self::assertEquals('original', $span->getName());

        $span->updateName('new');
        self::assertEquals('new', $span->getName());
    }
}
