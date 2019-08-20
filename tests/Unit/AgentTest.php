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

/** @covers \Scoutapm\Agent */
final class AgentTest extends TestCase
{
    public function testFullAgentSequence() : void
    {
        $agent = new Agent();

        // Start a Parent Controller Span
        $agent->startSpan('Controller/Test');

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

        self::assertNotNull($agent);
    }

    public function testInstrument() : void
    {
        $agent  = new Agent();
        $retval = $agent->instrument('Custom', 'Test', static function (Span $span) {
            $span->tag('OMG', 'Thingy');

            self::assertEquals($span->getName(), 'Custom/Test');

            return 'arbitrary return value';
        });

        // Check that the instrument helper propagates the return value
        self::assertEquals($retval, 'arbitrary return value');

        // Check that the span was stopped and tagged
        $events    = $agent->getRequest()->getEvents();
        $foundSpan = end($events);
        self::assertInstanceOf(Span::class, $foundSpan);
        self::assertNotNull($foundSpan->getStopTime());
        self::assertEquals($foundSpan->getTags()[0]->getTag(), 'OMG');
        self::assertEquals($foundSpan->getTags()[0]->getValue(), 'Thingy');
    }

    public function testWebTransaction() : void
    {
        $retval = (new Agent())->webTransaction('Test', function ($span) {
            // Check span name is prefixed with "Controller"
            $this->assertEquals($span->getName(), 'Controller/Test');

            return 'arbitrary return value';
        });
        // Check that the instrument helper propagates the return value
        self::assertEquals($retval, 'arbitrary return value');
    }

    public function testBackgroundTransaction() : void
    {
        $retval = (new Agent())->backgroundTransaction('Test', function ($span) {
            // Check span name is prefixed with "Job"
            $this->assertEquals($span->getName(), 'Job/Test');

            return 'arbitrary return value';
        });
        // Check that the instrument helper propagates the return value
        self::assertEquals($retval, 'arbitrary return value');
    }

    public function testCanSetLogger() : void
    {
        $agent  = new Agent();
        $logger = new NullLogger();

        $agent->setLogger($logger);

        self::assertSame($agent->getLogger(), $logger);
    }

    public function testCanGetConfig() : void
    {
        $config = (new Agent())->getConfig();
        self::assertInstanceOf(Config::class, $config);
    }

    public function testStartSpan() : void
    {
        $span  = (new Agent())->startSpan('foo/bar');
        self::assertEquals('foo/bar', $span->getName());
    }

    public function testStopSpan() : void
    {
        $agent = new Agent();
        $span  = $agent->startSpan('foo/bar');
        self::assertNull($span->getStopTime());

        $agent->stopSpan();

        self::assertNotNull($span->getStopTime());
    }

    public function testTagRequest() : void
    {
        $agent = new Agent();
        $agent->tagRequest('foo', 'bar');

        $events  = $agent->getRequest()->getEvents();

        $tag = end($events);

        self::assertInstanceOf(TagRequest::class, $tag);
        self::assertEquals('foo', $tag->getTag());
        self::assertEquals('bar', $tag->getValue());
    }

    public function testEnabled() : void
    {
        // without affirmatively enabling, it's not enabled.
        $agent = new Agent();
        self::assertFalse($agent->enabled());

        // but a config that has monitor = true, it is set
        $config = new Config($agent);
        $config->set('monitor', 'true');
        $agent->setConfig($config);

        self::assertTrue($agent->enabled());
    }

    public function testIgnoredEndpoints() : void
    {
        $agent = new Agent();
        $agent->getConfig()->set('ignore', ['/foo']);

        self::assertEquals(true, $agent->ignored('/foo'));
        self::assertEquals(false, $agent->ignored('/bar'));
    }

    /**
     * Many instrumentation calls are NOOPs when ignore is called. Make sure the sequence works as expected
     */
    public function testIgnoredAgentSequence() : void
    {
        $agent = new Agent();
        $agent->ignore();

        // Start a Parent Controller Span
        $agent->startSpan('Controller/Test');

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

        self::assertNotNull($agent);
    }
}
