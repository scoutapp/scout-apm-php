<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events;

use Exception;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Scoutapm\Events\TagRequest;

/** @covers \Scoutapm\Events\TagRequest */
final class TagRequestTest extends TestCase
{
    /** @throws Exception */
    public function testCanBeInitialized() : void
    {
        $tag = new TagRequest('t', 'v', Uuid::uuid4());
        self::assertNotNull($tag);
    }

    /** @throws Exception */
    public function testJsonSerializes() : void
    {
        $requestId  = Uuid::uuid4();
        $serialized = (new TagRequest('t', 'v', $requestId))->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('TagRequest', $serialized[0]);

        $data = $serialized[0]['TagRequest'];
        self::assertSame('t', $data['tag']);
        self::assertSame('v', $data['value']);
        self::assertSame($requestId->toString(), $data['request_id']);
    }
}
