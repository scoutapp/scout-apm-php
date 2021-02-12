<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events\Tag;

use Exception;
use PHPUnit\Framework\TestCase;
use Scoutapm\Events\Request\RequestId;
use Scoutapm\Events\Span\SpanId;
use Scoutapm\Events\Tag\TagSpan;

/**
 * @covers \Scoutapm\Events\Tag\TagSpan
 * @covers \Scoutapm\Events\Tag\Tag
 */
final class TagSpanTest extends TestCase
{
    /** @throws Exception */
    public function testCanBeInitialized(): void
    {
        $tag = new TagSpan('t', 'v', RequestId::new(), SpanId::new());

        self::assertSame('t', $tag->getTag());
        self::assertSame('v', $tag->getValue());
    }

    /** @throws Exception */
    public function testJsonSerializes(): void
    {
        $requestId = RequestId::new();
        $spanId    = SpanId::new();

        $serialized = (new TagSpan('t', 'v', $requestId, $spanId))->jsonSerialize();

        self::assertArrayHasKey('TagSpan', $serialized[0]);

        $data = $serialized[0]['TagSpan'];
        self::assertSame('t', $data['tag']);
        self::assertSame('v', $data['value']);
        self::assertSame($requestId->toString(), $data['request_id']);
        self::assertSame($spanId->toString(), $data['span_id']);
    }
}
