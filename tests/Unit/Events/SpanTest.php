<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events;

use Exception;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Scoutapm\Events\Span;

/** @covers \Scoutapm\Events\Span */
final class SpanTest extends TestCase
{
    /** @throws Exception */
    public function testCanBeInitialized() : void
    {
        $span = new Span('name', Uuid::uuid4());
        self::assertNotNull($span);
    }

    /** @throws Exception */
    public function testCanBeStopped() : void
    {
        $span = new Span('', Uuid::uuid4());
        $span->stop();
        self::assertNotNull($span);
    }

    /** @throws Exception */
    public function testJsonSerializes() : void
    {
        $span = new Span('', Uuid::uuid4());
        $span->tag('Foo', 'Bar');
        $span->stop();

        $serialized = $span->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('StartSpan', $serialized[0]);
        self::assertArrayHasKey('TagSpan', $serialized[1]);
        self::assertArrayHasKey('StopSpan', $serialized[2]);
    }

    /** @throws Exception */
    public function testSpanNameOverride() : void
    {
        $span = new Span('original', Uuid::uuid4());
        self::assertSame('original', $span->getName());

        $span->updateName('fromRequest');
        self::assertSame('fromRequest', $span->getName());
    }
}
