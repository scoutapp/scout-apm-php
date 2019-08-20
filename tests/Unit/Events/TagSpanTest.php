<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events;

use Exception;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Scoutapm\Events\Tag\SpanTag;

/** @covers \Scoutapm\Events\Tag\SpanTag */
final class TagSpanTest extends TestCase
{
    /** @throws Exception */
    public function testCanBeInitialized() : void
    {
        $tag = new SpanTag('t', 'v', Uuid::uuid4(), Uuid::uuid4());
        self::assertNotNull($tag);
    }

    /** @throws Exception */
    public function testJsonSerializes() : void
    {
        $requestId = Uuid::uuid4();
        $spanId    = Uuid::uuid4();

        $serialized = (new SpanTag('t', 'v', $requestId, $spanId))->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('TagSpan', $serialized[0]);

        $data = $serialized[0]['TagSpan'];
        self::assertSame('t', $data['tag']);
        self::assertSame('v', $data['value']);
        self::assertSame($requestId->toString(), $data['request_id']);
        self::assertSame($spanId->toString(), $data['span_id']);
    }
}
