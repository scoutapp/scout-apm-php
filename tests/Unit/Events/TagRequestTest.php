<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events;

use PHPUnit\Framework\TestCase;
use Scoutapm\Agent;
use Scoutapm\Events\TagRequest;

/** @covers \Scoutapm\Events\TagRequest */
final class TagRequestTest extends TestCase
{
    public function testCanBeInitialized() : void
    {
        $tag = new TagRequest(new Agent(), 't', 'v', 'reqid');
        self::assertNotNull($tag);
    }

    public function testJsonSerializes() : void
    {
        $serialized = (new TagRequest(new Agent(), 't', 'v', 'reqid'))->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('TagRequest', $serialized[0]);

        $data = $serialized[0]['TagRequest'];
        self::assertEquals('t', $data['tag']);
        self::assertEquals('v', $data['value']);
        self::assertEquals('reqid', $data['request_id']);
    }
}
