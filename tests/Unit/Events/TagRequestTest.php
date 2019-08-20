<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events;

use Exception;
use PHPUnit\Framework\TestCase;
use Scoutapm\Events\Request\RequestId;
use Scoutapm\Events\Tag\RequestTag;

/** @covers \Scoutapm\Events\Tag\RequestTag */
final class TagRequestTest extends TestCase
{
    /** @throws Exception */
    public function testCanBeInitialized() : void
    {
        $tag = new RequestTag('t', 'v', RequestId::new());
        self::assertNotNull($tag);
    }

    /** @throws Exception */
    public function testJsonSerializes() : void
    {
        $requestId  = RequestId::new();
        $serialized = (new RequestTag('t', 'v', $requestId))->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('RequestTag', $serialized[0]);

        $data = $serialized[0]['RequestTag'];
        self::assertSame('t', $data['tag']);
        self::assertSame('v', $data['value']);
        self::assertSame($requestId->toString(), $data['request_id']);
    }
}
