<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Scoutapm\Agent;
use Scoutapm\Config;
use Scoutapm\Events\Span;
use Scoutapm\Events\TagRequest;
use function end;

/**
 * Test Case for @see \Scoutapm\Agent
 */
final class AgentTest extends TestCase
{
    public function testFullAgentSequence() : void
    {
        $agent = new Agent();

        // Start a Parent Controller Span
        $span = $agent->startSpan('Controller/Test');

        // Tag Whole Request
        $agent->tagRequest('uri', 'example.com/foo/bar.php');

        // Start a Child Span
        $span = $agent->startSpan('SQL/Query');

        // Tag the span
        $span->tag('sql.query', 'select * from foo');

        // Finish Child Span
        $agent->stopSpan();

        // Stop Controller Span
        $agent->stopSpan();

        $this->assertNotNull($agent);
    }

    public function testInstrument() : void
    {
        $agent  = new Agent();
        $retval = $agent->instrument('Custom', 'Test', function ($span) {
            $span->tag('OMG', 'Thingy');

            $this->assertEquals($span->getName(), 'Custom/Test');

            return 'arbitrary return value';
        });

        // Check that the instrument helper propagates the return value
        $this->assertEquals($retval, 'arbitrary return value');

        // Check that the span was stopped and tagged
        $request   = $agent->getRequest();
        $events    = $request->getEvents();
        $foundSpan = end($events);
        $this->assertInstanceOf(Span::class, $foundSpan);
        $this->assertNotNull($foundSpan->getStopTime());
        $this->assertEquals($foundSpan->getTags()[0]->getTag(), 'OMG');
        $this->assertEquals($foundSpan->getTags()[0]->getValue(), 'Thingy');
    }

    public function testWebTransaction() : void
    {
        $agent  = new Agent();
        $retval = $agent->webTransaction('Test', function ($span) {
            // Check span name is prefixed with "Controller"
            $this->assertEquals($span->getName(), 'Controller/Test');

            return 'arbitrary return value';
        });
        // Check that the instrument helper propagates the return value
        $this->assertEquals($retval, 'arbitrary return value');
    }

    public function testBackgroundTransaction() : void
    {
        $agent  = new Agent();
        $retval = $agent->backgroundTransaction('Test', function ($span) {
            // Check span name is prefixed with "Job"
            $this->assertEquals($span->getName(), 'Job/Test');

            return 'arbitrary return value';
        });
        // Check that the instrument helper propagates the return value
        $this->assertEquals($retval, 'arbitrary return value');
    }

    public function testCanSetLogger() : void
    {
        $agent  = new Agent();
        $logger = new NullLogger();

        $agent->setLogger($logger);

        $this->assertEquals($agent->getLogger(), $logger);
    }

    public function testCanGetConfig() : void
    {
        $agent  = new Agent();
        $config = $agent->getConfig();
        $this->assertInstanceOf(Config::class, $config);
    }

    public function testStartSpan() : void
    {
        $agent = new Agent();
        $span  = $agent->startSpan('foo/bar');
        $this->assertEquals('foo/bar', $span->getName());
        $this->assertInstanceOf(Span::class, $span);
    }

    public function testStopSpan() : void
    {
        $agent = new Agent();
        $span  = $agent->startSpan('foo/bar');
        $this->assertNull($span->getStopTime());

        $agent->stopSpan();

        $this->assertNotNull($span->getStopTime());
    }

    public function testTagRequest() : void
    {
        $agent = new Agent();
        $agent->tagRequest('foo', 'bar');

        $request = $agent->getRequest();
        $events  = $request->getEvents();

        $tag = end($events);

        $this->assertInstanceOf(TagRequest::class, $tag);
        $this->assertEquals('foo', $tag->getTag());
        $this->assertEquals('bar', $tag->getValue());
    }

    public function testEnabled() : void
    {
        // without affirmatively enabling, it's not enabled.
        $agent = new Agent();
        $this->assertEquals(false, $agent->enabled());

        // but a config that has monitor = true, it is set
        $config = new Config($agent);
        $config->set('monitor', 'true');
        $agent->setConfig($config);

        $this->assertEquals(true, $agent->enabled());
    }

    public function testIgnoredEndpoints() : void
    {
        $agent = new Agent();
        $agent->getConfig()->set('ignore', ['/foo']);

        $this->assertEquals(true, $agent->ignored('/foo'));
        $this->assertEquals(false, $agent->ignored('/bar'));
    }

    /**
     * Many instrumentation calls are NOOPs when ignore is called. Make sure the sequence works as expected
     */
    public function testIgnoredAgentSequence() : void
    {
        $agent = new Agent();
        $agent->ignore();

        // Start a Parent Controller Span
        $span = $agent->startSpan('Controller/Test');

        // Tag Whole Request
        $agent->tagRequest('uri', 'example.com/foo/bar.php');

        // Start a Child Span
        $span = $agent->startSpan('SQL/Query');

        // Tag the span
        $span->tag('sql.query', 'select * from foo');

        // Finish Child Span
        $agent->stopSpan();

        // Stop Controller Span
        $agent->stopSpan();

        $agent->send();

        $this->assertNotNull($agent);
    }
}
