<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events;

use Exception;
use PHPUnit\Framework\TestCase;
use Scoutapm\Events\Request\RequestId;
use Scoutapm\Events\Span\Span;

/** @covers \Scoutapm\Events\Span\Span */
final class SpanTest extends TestCase
{
    /** @throws Exception */
    public function testCanBeInitialized() : void
    {
        $span = new Span('name', RequestId::new());
        self::assertNotNull($span);
    }

    /** @throws Exception */
    public function testCanBeStopped() : void
    {
        $span = new Span('', RequestId::new());
        $span->stop();
        self::assertNotNull($span);
    }

    /** @throws Exception */
    public function testJsonSerializes() : void
    {
        $span = new Span('', RequestId::new());
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
        $span = new Span('original', RequestId::new());
        self::assertSame('original', $span->getName());

        $span->updateName('fromRequest');
        self::assertSame('fromRequest', $span->getName());
    }
}
