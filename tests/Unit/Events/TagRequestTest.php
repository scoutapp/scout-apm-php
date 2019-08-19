<?php
namespace Scoutapm\UnitTests\Events;

use \PHPUnit\Framework\TestCase;
use \Scoutapm\Agent;
use \Scoutapm\Events\TagRequest;

/**
 * Test Case for @see \Scoutapm\Events\TagRequest
 */
final class TagRequestTest extends TestCase
{
    public function testCanBeInitialized()
    {
        $tag = new TagRequest(new Agent(), 't', 'v', 'reqid');
        $this->assertNotNull($tag);
    }

    public function testJsonSerializes()
    {
        $tag = new TagRequest(new Agent(), 't', 'v', 'reqid');

        $serialized = $tag->jsonSerialize();

        $this->assertIsArray($serialized);
        $this->assertArrayHasKey("TagRequest", $serialized[0]);

        $data = $serialized[0]["TagRequest"];
        $this->assertEquals("t", $data["tag"]);
        $this->assertEquals("v", $data["value"]);
        $this->assertEquals("reqid", $data["request_id"]);
    }
}
