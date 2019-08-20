<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events;

use PHPUnit\Framework\TestCase;
use Scoutapm\Agent;
use Scoutapm\Events\TagSpan;

/** @covers \Scoutapm\Events\TagSpan */
final class TagSpanTest extends TestCase
{
    public function testCanBeInitialized() : void
    {
        $tag = new TagSpan(new Agent(), 't', 'v', 'reqid', 'spanid');
        self::assertNotNull($tag);
    }

    public function testJsonSerializes() : void
    {
        $serialized = (new TagSpan(new Agent(), 't', 'v', 'reqid', 'spanid'))->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('TagSpan', $serialized[0]);

        $data = $serialized[0]['TagSpan'];
        self::assertSame('t', $data['tag']);
        self::assertSame('v', $data['value']);
        self::assertSame('reqid', $data['request_id']);
        self::assertSame('spanid', $data['span_id']);
    }
}
