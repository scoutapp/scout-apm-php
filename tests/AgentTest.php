<?php
namespace Scoutapm\Tests;

use \PHPUnit\Framework\TestCase;
use \Scoutapm\Agent;
use Psr\Log\NullLogger;

/**
 * Test Case for @see \Scoutapm\Agent
 */
final class AgentTest extends TestCase
{
    public function testFullAgentSequence()
    {
        $agent = new Agent();

        // Start a Parent Controller Span
        $span = $agent->startSpan("Controller/Test");

        // Tag Whole Request
        $agent->tagRequest("uri", "example.com/foo/bar.php");

        // Start a Child Span
        $span = $agent->startSpan("SQL/Query");

        // Tag the span
        $span->tag("sql.query", "select * from foo");

        // Finish Child Span
        $agent->stopSpan();

        // Stop Controller Span
        $agent->stopSpan();

        $this->assertNotNull($agent);
    }

    public function testCanSetLogger()
    {
        $agent = new Agent();
        $logger = new NullLogger();

        $agent->setLogger($logger);

        $this->assertEquals($agent->getLogger(), $logger);
    }

    public function testCanGetConfig()
    {
        $agent = new Agent();
        $config = $agent->getConfig();
        $this->assertInstanceOf(\Scoutapm\Config::class, $config);
    }

    public function testStartSpan()
    {
        $agent = new Agent();
        $span = $agent->startSpan("foo/bar");
        $this->assertEquals("foo/bar", $span->getName());
        $this->assertInstanceOf(\Scoutapm\Events\Span::class, $span);
    }

    public function testStopSpan()
    {
        $agent = new Agent();
        $span = $agent->startSpan("foo/bar");
        $this->assertNull($span->getStopTime());

        $agent->stopSpan();

        $this->assertNotNull($span->getStopTime());
    }

    public function testTagRequest()
    {
        $agent = new Agent();
        $agent->tagRequest("foo", "bar");
        
        $request = $agent->getRequest();
        $events = $request->getEvents();

        $tag = end($events);

        $this->assertInstanceOf(\Scoutapm\Events\TagRequest::class, $tag);
        $this->assertEquals("foo", $tag->getTag());
        $this->assertEquals("bar", $tag->getValue());
    }
}
