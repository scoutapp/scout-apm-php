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

    public function testInstrument()
    {
        $agent = new Agent();
        $retval = $agent->instrument("Custom", "Test", function ($span) {
            $span->tag("OMG", "Thingy");

            $this->assertEquals($span->getName(), "Custom/Test");
            return "arbitrary return value";
        });

        // Check that the instrument helper propagates the return value
        $this->assertEquals($retval, "arbitrary return value");

        // Check that the span was stopped and tagged
        $request = $agent->getRequest();
        $events = $request->getEvents();
        $foundSpan = end($events);
        $this->assertInstanceOf(\Scoutapm\Events\Span::class, $foundSpan);
        $this->assertNotNull($foundSpan->getStopTime());
        $this->assertEquals($foundSpan->getTags()[0]->getTag(), "OMG");
        $this->assertEquals($foundSpan->getTags()[0]->getValue(), "Thingy");
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

    public function testEnabled()
    {
        // without affirmatively enabling, it's not enabled.
        $agent = new Agent();
        $this->assertEquals(false, $agent->enabled());

        // but a config that has monitor = true, it is set
        $config = new \Scoutapm\Config($agent);
        $config->set("monitor", "true");
        $agent->setConfig($config);

        $this->assertEquals(true, $agent->enabled());
    }
}
