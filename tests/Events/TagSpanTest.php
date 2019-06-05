<?php
namespace Scoutapm\Events\Tests;

use \PHPUnit\Framework\TestCase;
use \Scoutapm\Agent;
use \Scoutapm\Events\TagSpan;

/**
 * Test Case for @see \Scoutapm\Events\TagSpan
 */
final class TagSpanTest extends TestCase
{
    public function testCanBeInitialized()
    {
        $tag = new TagSpan(new Agent(), 't', 'v', 'reqid', 'spanid');
        $this->assertNotNull($tag);
    }

    public function testJsonSerializes()
    {
        $tag = new TagSpan(new Agent(), 't', 'v', 'reqid', 'spanid');

        $serialized = $tag->jsonSerialize();

        $this->assertIsArray($serialized);
        $this->assertArrayHasKey("TagSpan", $serialized[0]);

        $data = $serialized[0]["TagSpan"];
        $this->assertEquals("t", $data["tag"]);
        $this->assertEquals("v", $data["value"]);
        $this->assertEquals("reqid", $data["request_id"]);
        $this->assertEquals("spanid", $data["span_id"]);
    }
}
