<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Scoutapm\Agent;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;
use Scoutapm\Events\Span\Span;
use Scoutapm\Events\Tag\TagRequest;
use function end;

/** @covers \Scoutapm\Agent */
final class AgentTest extends TestCase
{
    public function testMinimumLogLevelCanBeSetOnConfigurationToSquelchNoisyLogMessages() : void
    {
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $logger->expects(self::never())
            ->method('log');

        $config = new Config();
        $config->set(ConfigKey::LOG_LEVEL, LogLevel::WARNING);
        $config->set(ConfigKey::MONITORING_ENABLED, 'false');

        $agent = Agent::fromConfig($config, $logger);
        $agent->connect();
    }

    public function testLogMessagesAreLoggedWhenUsingDefaultConfiguration() : void
    {
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);

        $logger->expects(self::once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                self::stringContains('Scout Core Agent Connected'),
                []
            );

        $config = new Config();
        $config->set(ConfigKey::MONITORING_ENABLED, 'false');

        $agent = Agent::fromConfig($config, $logger);
        $agent->connect();
    }

    public function testFullAgentSequence() : void
    {
        $agent = Agent::fromDefaults();

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
        $agent  = Agent::fromDefaults();
        $retval = $agent->instrument('Custom', 'Test', static function (Span $span) {
            $span->tag('OMG', 'Thingy');

            self::assertSame($span->getName(), 'Custom/Test');

            return 'arbitrary return value';
        });

        // Check that the instrument helper propagates the return value
        self::assertSame($retval, 'arbitrary return value');

        // Check that the span was stopped and tagged
        $events    = $agent->getRequest()->getEvents();
        $foundSpan = end($events);
        self::assertInstanceOf(Span::class, $foundSpan);
        self::assertNotNull($foundSpan->getStopTime());
        self::assertSame($foundSpan->getTags()[0]->getTag(), 'OMG');
        self::assertSame($foundSpan->getTags()[0]->getValue(), 'Thingy');
    }

    public function testWebTransaction() : void
    {
        $retval = Agent::fromDefaults()->webTransaction('Test', static function (Span $span) {
            // Check span name is prefixed with "Controller"
            self::assertSame($span->getName(), 'Controller/Test');

            return 'arbitrary return value';
        });
        // Check that the instrument helper propagates the return value
        self::assertSame($retval, 'arbitrary return value');
    }

    public function testBackgroundTransaction() : void
    {
        $retval = Agent::fromDefaults()->backgroundTransaction('Test', static function (Span $span) {
            // Check span name is prefixed with "Job"
            self::assertSame($span->getName(), 'Job/Test');

            return 'arbitrary return value';
        });
        // Check that the instrument helper propagates the return value
        self::assertSame($retval, 'arbitrary return value');
    }

    public function testStartSpan() : void
    {
        $span = Agent::fromDefaults()->startSpan('foo/bar');
        self::assertSame('foo/bar', $span->getName());
    }

    public function testStopSpan() : void
    {
        $agent = Agent::fromDefaults();
        $span  = $agent->startSpan('foo/bar');
        self::assertNull($span->getStopTime());

        $agent->stopSpan();

        self::assertNotNull($span->getStopTime());
    }

    public function testTagRequest() : void
    {
        $agent = Agent::fromDefaults();
        $agent->tagRequest('foo', 'bar');

        $events = $agent->getRequest()->getEvents();

        $tag = end($events);

        self::assertInstanceOf(TagRequest::class, $tag);
        self::assertSame('foo', $tag->getTag());
        self::assertSame('bar', $tag->getValue());
    }

    public function testEnabled() : void
    {
        // without affirmatively enabling, it's not enabled.
        $agentWithoutEnabling = Agent::fromDefaults();
        self::assertFalse($agentWithoutEnabling->enabled());

        // but a config that has monitor = true, it is set
        $config = new Config();
        $config->set(ConfigKey::MONITORING_ENABLED, 'true');

        $enabledAgent = Agent::fromConfig($config);
        self::assertTrue($enabledAgent->enabled());
    }

    public function testIgnoredEndpoints() : void
    {
        $config = new Config();
        $config->set(ConfigKey::IGNORED_ENDPOINTS, ['/foo']);

        $agent = Agent::fromConfig($config);

        self::assertTrue($agent->ignored('/foo'));
        self::assertFalse($agent->ignored('/bar'));
    }

    /**
     * Many instrumentation calls are NOOPs when ignore is called. Make sure the sequence works as expected
     */
    public function testIgnoredAgentSequence() : void
    {
        $agent = Agent::fromDefaults();
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
