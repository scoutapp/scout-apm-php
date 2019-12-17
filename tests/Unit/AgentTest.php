<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests;

use Exception;
use OutOfBoundsException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Psr\Log\Test\TestLogger;
use Scoutapm\Agent;
use Scoutapm\Cache\DevNullCache;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;
use Scoutapm\Connector\Connector;
use Scoutapm\Events\Metadata;
use Scoutapm\Events\RegisterMessage;
use Scoutapm\Events\Request\Request;
use Scoutapm\Events\Span\Span;
use Scoutapm\Events\Tag\TagRequest;
use Scoutapm\ScoutApmAgent;
use function array_map;
use function end;
use function sprintf;
use function uniqid;

/** @covers \Scoutapm\Agent */
final class AgentTest extends TestCase
{
    /** @var TestLogger */
    private $logger;

    /** @var Connector&MockObject */
    private $connector;

    public function setUp() : void
    {
        parent::setUp();

        $this->logger    = new TestLogger();
        $this->connector = $this->createMock(Connector::class);
    }

    /** @param mixed[]|array<string, mixed> $config */
    private function agentFromConfigArray(array $config = []) : ScoutApmAgent
    {
        return Agent::fromConfig(Config::fromArray($config), $this->logger, new DevNullCache(), $this->connector);
    }

    /**
     * @return Config[][]|string[][][]
     *
     * @psalm-return array<string, array{config: array<string, mixed>, missingKeys: array<int, string>}>
     */
    public function invalidConfigurationProvider() : array
    {
        return [
            'withoutName' => [
                'config' => [
                    ConfigKey::MONITORING_ENABLED => true,
                    ConfigKey::APPLICATION_KEY => 'abc123',
                ],
                'missingKeys' => [
                    ConfigKey::APPLICATION_NAME,
                ],
            ],
            'withoutKey' => [
                'config' => [
                    ConfigKey::MONITORING_ENABLED => true,
                    ConfigKey::APPLICATION_NAME => 'My Application',
                ],
                'missingKeys' => [
                    ConfigKey::APPLICATION_KEY,
                ],
            ],
            'withoutAnything' => [
                'config' => [ConfigKey::MONITORING_ENABLED => true],
                'missingKeys' => [
                    ConfigKey::APPLICATION_NAME,
                    ConfigKey::APPLICATION_KEY,
                ],
            ],
        ];
    }

    /**
     * @param mixed[]|array<string, mixed> $config
     * @param string[]|array<int, string>  $missingKeys
     *
     * @dataProvider invalidConfigurationProvider
     */
    public function testCreatingAgentWithoutRequiredConfigKeysLogsWarning(array $config, array $missingKeys) : void
    {
        $this->agentFromConfigArray($config);

        array_map(
            function (string $missingKey) : void {
                self::assertTrue($this->logger->hasWarningThatContains(sprintf(
                    'Config key "%s" should be set, but it was empty',
                    $missingKey
                )));
            },
            $missingKeys
        );
    }

    public function testMinimumLogLevelCanBeSetOnConfigurationToSquelchNoisyLogMessages() : void
    {
        $agent = $this->agentFromConfigArray([
            ConfigKey::APPLICATION_NAME => 'My Application',
            ConfigKey::APPLICATION_KEY => 'abc123',
            ConfigKey::LOG_LEVEL => LogLevel::WARNING,
            ConfigKey::MONITORING_ENABLED => false,
        ]);

        $agent->connect();

        self::assertFalse($this->logger->hasDebugRecords());
    }

    public function testLogMessagesAreLoggedWhenUsingDefaultConfiguration() : void
    {
        $agent = $this->agentFromConfigArray([
            ConfigKey::APPLICATION_NAME => 'My Application',
            ConfigKey::APPLICATION_KEY => 'abc123',
            ConfigKey::MONITORING_ENABLED => false,
        ]);

        $agent->connect();

        self::assertTrue($this->logger->hasDebugThatContains('Configuration'));
        self::assertTrue($this->logger->hasDebugThatContains('Connection skipped, since monitoring is disabled'));
    }

    /** @throws Exception */
    public function testFullAgentSequence() : void
    {
        $agent = $this->agentFromConfigArray([
            ConfigKey::APPLICATION_NAME => 'My test app',
            ConfigKey::APPLICATION_KEY => uniqid('applicationKey', true),
            ConfigKey::MONITORING_ENABLED => true,
        ]);

        $this->connector->method('connected')->willReturn(true);

        $this->connector->expects(self::at(1))
            ->method('sendCommand')
            ->with(self::isInstanceOf(RegisterMessage::class))
            ->willReturn('{"Register":"Success"}');
        $this->connector->expects(self::at(2))
            ->method('sendCommand')
            ->with(self::isInstanceOf(Metadata::class))
            ->willReturn('{"Metadata":"Success"}');
        $this->connector->expects(self::at(3))
            ->method('sendCommand')
            ->with(self::isInstanceOf(Request::class))
            ->willReturn('{"Request":"Success"}');

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

        self::assertTrue($agent->send());

        self::assertTrue($this->logger->hasDebugThatContains('Sent whole payload successfully to core agent'));
    }

    public function testInstrumentNamesSpanAndReturnsValueFromClosureAndStopsSpan() : void
    {
        $agent = $this->agentFromConfigArray();

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

    public function testWebTransactionNamesSpanCorrectlyAndReturnsValueFromClosure() : void
    {
        self::assertSame(
            $this->agentFromConfigArray()->webTransaction('Test', static function (Span $span) {
                // Check span name is prefixed with "Controller"
                self::assertSame($span->getName(), 'Controller/Test');

                return 'arbitrary return value';
            }),
            'arbitrary return value'
        );
    }

    public function testBackgroundTransactionNamesSpanCorrectlyAndReturnsValueFromClosure() : void
    {
        self::assertSame(
            $this->agentFromConfigArray()->backgroundTransaction('Test', static function (Span $span) {
                // Check span name is prefixed with "Job"
                self::assertSame($span->getName(), 'Job/Test');

                return 'arbitrary return value';
            }),
            'arbitrary return value'
        );
    }

    public function testStartSpan() : void
    {
        $span = $this->agentFromConfigArray()->startSpan('foo/bar');
        self::assertSame('foo/bar', $span->getName());
    }

    public function testStopSpan() : void
    {
        $agent = $this->agentFromConfigArray();
        $span  = $agent->startSpan('foo/bar');
        self::assertNull($span->getStopTime());

        $agent->stopSpan();

        self::assertNotNull($span->getStopTime());
    }

    public function testTagRequest() : void
    {
        $agent = $this->agentFromConfigArray();
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
        $agentWithoutEnabling = $this->agentFromConfigArray();
        self::assertFalse($agentWithoutEnabling->enabled());

        // but a config that has monitor = true, it is set
        $enabledAgent = $this->agentFromConfigArray([ConfigKey::MONITORING_ENABLED => 'true']);
        self::assertTrue($enabledAgent->enabled());
    }

    public function testIgnoredEndpoints() : void
    {
        $agent = $this->agentFromConfigArray([
            ConfigKey::IGNORED_ENDPOINTS => ['/foo'],
        ]);

        self::assertTrue($agent->ignored('/foo'));
        self::assertFalse($agent->ignored('/bar'));
    }

    /** @throws Exception */
    public function testMetadataExceptionsAreLogged() : void
    {
        $agent = $this->agentFromConfigArray([
            ConfigKey::APPLICATION_NAME => 'My test app',
            ConfigKey::APPLICATION_KEY => uniqid('applicationKey', true),
            ConfigKey::MONITORING_ENABLED => true,
            ConfigKey::LOG_LEVEL => LogLevel::NOTICE,
        ]);

        $this->connector->method('connected')->willReturn(true);

        $this->connector->expects(self::at(1))
            ->method('sendCommand')
            ->with(self::isInstanceOf(RegisterMessage::class))
            ->willReturn('{"Register":"Success"}');
        $this->connector->expects(self::at(2))
            ->method('sendCommand')
            ->with(self::isInstanceOf(Metadata::class))
            ->willThrowException(new OutOfBoundsException('Some obscure exception happened'));
        $this->connector->expects(self::at(3))
            ->method('sendCommand')
            ->with(self::isInstanceOf(Request::class))
            ->willReturn('{"Request":"Success"}');

        self::assertTrue($agent->send());

        self::assertTrue($this->logger->hasNoticeThatContains('Sending metadata raised an exception: Some obscure exception happened'));
    }

    /**
     * Many instrumentation calls are NOOPs when ignore is called. Make sure the sequence works as expected
     *
     * @throws Exception
     */
    public function testIgnoredAgentSequence() : void
    {
        $agent = $this->agentFromConfigArray([ConfigKey::MONITORING_ENABLED => true]);
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

        self::assertFalse($agent->send());

        self::assertTrue($this->logger->hasDebugThatContains('Not sending payload, request has been ignored'));
    }

    /** @throws Exception */
    public function testRequestIsResetAfterCallingSend() : void
    {
        $agent = $this->agentFromConfigArray([
            ConfigKey::APPLICATION_NAME => 'My test app',
            ConfigKey::APPLICATION_KEY => uniqid('applicationKey', true),
            ConfigKey::MONITORING_ENABLED => true,
        ]);

        $requestBeforeSend = $agent->getRequest();

        $this->connector->method('connected')->willReturn(true);
        $this->connector->expects(self::exactly(3))->method('sendCommand');

        self::assertTrue($agent->send());

        self::assertNotSame($requestBeforeSend, $agent->getRequest());
    }

    public function testRegisterEventIsOnlySentOnceWhenSendingTwoRequestsWithSameAgent() : void
    {
        $agent = $this->agentFromConfigArray([
            ConfigKey::APPLICATION_NAME => 'My test app',
            ConfigKey::APPLICATION_KEY => uniqid('applicationKey', true),
            ConfigKey::MONITORING_ENABLED => true,
        ]);

        $this->connector->method('connected')->willReturn(true);

        // First send() call expectations
        $this->connector->expects(self::at(1))
            ->method('sendCommand')
            ->with(self::isInstanceOf(RegisterMessage::class));
        $this->connector->expects(self::at(2))
            ->method('sendCommand')
            ->with(self::isInstanceOf(Metadata::class));
        $this->connector->expects(self::at(3))
            ->method('sendCommand')
            ->with(self::isInstanceOf(Request::class));

        // Second send() call expectations - note Metadata is sent again because we are using DevNullCache
        $this->connector->expects(self::at(5))
            ->method('sendCommand')
            ->with(self::isInstanceOf(Metadata::class));
        $this->connector->expects(self::at(6))
            ->method('sendCommand')
            ->with(self::isInstanceOf(Request::class));

        $agent->send();
        $agent->send();
    }
}
