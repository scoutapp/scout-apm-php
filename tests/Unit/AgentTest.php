<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests;

use Cache\Adapter\PHPArray\ArrayCachePool;
use Exception;
use OutOfBoundsException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Scoutapm\Agent;
use Scoutapm\Cache\DevNullCache;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;
use Scoutapm\Connector\Command;
use Scoutapm\Connector\Connector;
use Scoutapm\Connector\Exception\FailedToConnect;
use Scoutapm\Connector\Exception\FailedToSendCommand;
use Scoutapm\Connector\Exception\NotConnected;
use Scoutapm\Events\Metadata;
use Scoutapm\Events\RegisterMessage;
use Scoutapm\Events\Request\Request;
use Scoutapm\Events\Span\Span;
use Scoutapm\Events\Span\SpanReference;
use Scoutapm\Events\Tag\Tag;
use Scoutapm\Events\Tag\TagRequest;
use Scoutapm\Extension\ExtensionCapabilities;
use Scoutapm\Extension\RecordedCall;
use Scoutapm\Extension\Version;
use Scoutapm\IntegrationTests\TestHelper;
use Scoutapm\ScoutApmAgent;

use function array_map;
use function assert;
use function end;
use function json_encode;
use function microtime;
use function next;
use function random_int;
use function reset;
use function sprintf;
use function uniqid;

/** @covers \Scoutapm\Agent */
final class AgentTest extends TestCase
{
    private const EXPECTED_SPAN_LIMIT = 3000;

    /** @var TestLogger */
    private $logger;

    /** @var Connector&MockObject */
    private $connector;

    /** @var ExtensionCapabilities&MockObject */
    private $phpExtension;

    public function setUp(): void
    {
        parent::setUp();

        $this->logger       = new TestLogger();
        $this->connector    = $this->createMock(Connector::class);
        $this->phpExtension = $this->createMock(ExtensionCapabilities::class);
    }

    private function requestFromAgent(ScoutApmAgent $agent): ?Request
    {
        /** @psalm-suppress DeprecatedMethod */
        return $agent->getRequest();
    }

    /** @return array<int, Command> */
    private function eventsFromAgent(ScoutApmAgent $agent): array
    {
        $request = $this->requestFromAgent($agent);
        assert($request !== null);

        /** @psalm-suppress DeprecatedMethod */
        return $request->getEvents();
    }

    /** @param mixed[]|array<string, mixed> $config */
    private function agentFromConfigArray(array $config = []): ScoutApmAgent
    {
        return Agent::fromConfig(
            Config::fromArray($config),
            $this->logger,
            new DevNullCache(),
            $this->connector,
            $this->phpExtension
        );
    }

    /**
     * @return Config[][]|string[][][]
     *
     * @psalm-return array<string, array{config: array<string, mixed>, missingKeys: array<int, string>}>
     */
    public function invalidConfigurationProvider(): array
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
    public function testCreatingAgentWithoutRequiredConfigKeysLogsWarning(array $config, array $missingKeys): void
    {
        $this->agentFromConfigArray($config);

        array_map(
            function (string $missingKey): void {
                self::assertTrue($this->logger->hasWarningThatContains(sprintf(
                    'Config key "%s" should be set, but it was empty',
                    $missingKey
                )));
            },
            $missingKeys
        );
    }

    public function testMinimumLogLevelCanBeSetOnConfigurationToSquelchNoisyLogMessages(): void
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

    public function testLogMessagesAreLoggedWhenUsingDefaultConfiguration(): void
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
    public function testFullAgentSequence(): void
    {
        $agent = $this->agentFromConfigArray([
            ConfigKey::APPLICATION_NAME => 'My test app',
            ConfigKey::APPLICATION_KEY => uniqid('applicationKey', true),
            ConfigKey::MONITORING_ENABLED => true,
            ConfigKey::LOG_LEVEL => LogLevel::DEBUG,
        ]);

        $microtime = microtime(true);

        $this->phpExtension->expects(self::at(0))
            ->method('getCalls')
            ->willReturn([
                RecordedCall::fromExtensionLoggedCallArray([
                    'function' => 'file_get_contents',
                    'entered' => $microtime - 1,
                    'exited' => $microtime,
                    'time_taken' => 1,
                    'argv' => ['http://some-url'],
                ]),
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
            ->with(self::callback(static function (Request $request): bool {
                TestHelper::assertUnserializedCommandContainsPayload(
                    'BatchCommand',
                    [
                        'commands' => static function (array $commands): bool {
                            TestHelper::assertUnserializedCommandContainsPayload('StartRequest', [], reset($commands), null);
                            TestHelper::assertUnserializedCommandContainsPayload('StartSpan', ['operation' => 'file_get_contents'], next($commands), null);
                            TestHelper::assertUnserializedCommandContainsPayload('TagSpan', ['tag' => 'args', 'value' => ['url' => 'http://some-url']], next($commands), null);
                            TestHelper::assertUnserializedCommandContainsPayload('TagSpan', ['tag' => 'stack'], next($commands), null);
                            TestHelper::assertUnserializedCommandContainsPayload('StopSpan', [], next($commands), null);
                            TestHelper::assertUnserializedCommandContainsPayload('StartSpan', ['operation' => 'Controller/Test'], next($commands), null);
                            TestHelper::assertUnserializedCommandContainsPayload('StartSpan', ['operation' => 'SQL/Query'], next($commands), null);
                            TestHelper::assertUnserializedCommandContainsPayload('TagSpan', ['tag' => 'sql.query', 'value' => 'select * from foo'], next($commands), null);
                            TestHelper::assertUnserializedCommandContainsPayload('StopSpan', [], next($commands), null);
                            TestHelper::assertUnserializedCommandContainsPayload('StopSpan', [], next($commands), null);
                            TestHelper::assertUnserializedCommandContainsPayload('TagRequest', ['tag' => 'uri', 'value' => 'example.com/foo/bar.php'], next($commands), null);
                            TestHelper::assertUnserializedCommandContainsPayload('TagRequest', ['tag' => 'memory_delta'], next($commands), null);
                            TestHelper::assertUnserializedCommandContainsPayload('TagRequest', ['tag' => 'path'], next($commands), null);
                            TestHelper::assertUnserializedCommandContainsPayload('FinishRequest', [], next($commands), null);

                            return true;
                        },
                    ],
                    $request->jsonSerialize(),
                    null
                );

                return true;
            }))
            ->willReturn('{"Request":"Success"}');

        // Start a Parent Controller Span
        $agent->startSpan('Controller/Test');

        // Tag Whole Request
        $agent->tagRequest('uri', 'example.com/foo/bar.php');

        // Start a Child Span
        $span = $agent->startSpan('SQL/Query');

        self::assertNotNull($span);

        // Tag the span
        $span->tag('sql.query', 'select * from foo');

        // Finish Child Span
        $agent->stopSpan();

        // Stop Controller Span
        $agent->stopSpan();

        self::assertTrue($agent->send());

        self::assertTrue($this->logger->hasDebugThatContains('Sent whole payload successfully to core agent'));
    }

    public function testInstrumentNamesSpanAndReturnsValueFromClosureAndStopsSpan(): void
    {
        $agent = $this->agentFromConfigArray();

        $retval = $agent->instrument('Custom', 'Test', static function (?SpanReference $span) {
            self::assertNotNull($span);

            $span->tag('OMG', 'Thingy');

            self::assertSame($span->getName(), 'Custom/Test');

            return 'arbitrary return value';
        });

        // Check that the instrument helper propagates the return value
        self::assertSame($retval, 'arbitrary return value');

        // Check that the span was stopped and tagged
        $events    = $this->eventsFromAgent($agent);
        $foundSpan = end($events);
        self::assertInstanceOf(Span::class, $foundSpan);
        self::assertNotNull($foundSpan->getStopTime());

        $firstTag = static function (Span $span): Tag {
            /** @psalm-suppress DeprecatedMethod */
            $tags = $span->getTags();

            return reset($tags);
        };
        $tag      = $firstTag($foundSpan);
        self::assertSame($tag->getTag(), 'OMG');
        self::assertSame($tag->getValue(), 'Thingy');
    }

    public function testWebTransactionNamesSpanCorrectlyAndReturnsValueFromClosure(): void
    {
        self::assertSame(
            $this->agentFromConfigArray()->webTransaction('Test', static function (?SpanReference $span) {
                self::assertNotNull($span);

                // Check span name is prefixed with "Controller"
                self::assertSame($span->getName(), 'Controller/Test');

                return 'arbitrary return value';
            }),
            'arbitrary return value'
        );
    }

    public function testBackgroundTransactionNamesSpanCorrectlyAndReturnsValueFromClosure(): void
    {
        self::assertSame(
            $this->agentFromConfigArray()->backgroundTransaction('Test', static function (?SpanReference $span) {
                self::assertNotNull($span);

                // Check span name is prefixed with "Job"
                self::assertSame($span->getName(), 'Job/Test');

                return 'arbitrary return value';
            }),
            'arbitrary return value'
        );
    }

    public function testStartSpan(): void
    {
        $span = $this->agentFromConfigArray()->startSpan('foo/bar');
        self::assertNotNull($span);
        self::assertSame('foo/bar', $span->getName());
    }

    public function testStopSpan(): void
    {
        $agent = $this->agentFromConfigArray();
        $span  = $agent->startSpan('foo/bar');
        self::assertNotNull($span);
        self::assertNull($span->getStopTime());

        $agent->stopSpan();

        self::assertNotNull($span->getStopTime());
    }

    public function testTagRequest(): void
    {
        $agent = $this->agentFromConfigArray();
        $agent->tagRequest('foo', 'bar');

        $events = $this->eventsFromAgent($agent);

        $tag = end($events);

        self::assertInstanceOf(TagRequest::class, $tag);
        self::assertSame('foo', $tag->getTag());
        self::assertSame('bar', $tag->getValue());
    }

    public function testEnabled(): void
    {
        // without affirmatively enabling, it's not enabled.
        $agentWithoutEnabling = $this->agentFromConfigArray();
        self::assertFalse($agentWithoutEnabling->enabled());

        // but a config that has monitor = true, it is set
        $enabledAgent = $this->agentFromConfigArray([ConfigKey::MONITORING_ENABLED => 'true']);
        self::assertTrue($enabledAgent->enabled());
    }

    public function testIgnoredEndpoints(): void
    {
        $agent = $this->agentFromConfigArray([
            ConfigKey::IGNORED_ENDPOINTS => ['/foo'],
        ]);

        self::assertTrue($agent->ignored('/foo'));
        self::assertFalse($agent->ignored('/bar'));
    }

    public function testInstrumentationIsNotDisabledWhenNoDisabledInstrumentsConfigured(): void
    {
        self::assertTrue(
            $this->agentFromConfigArray([])
                ->shouldInstrument('some functionality')
        );
    }

    public function testInstrumentationIsNotDisabledWhenDisabledInstrumentsConfigurationIsWrong(): void
    {
        self::assertTrue(
            $this->agentFromConfigArray([ConfigKey::DISABLED_INSTRUMENTS => 'disabled functionality'])
                ->shouldInstrument('some functionality')
        );
    }

    public function testInstrumentationIsNotDisabledWhenDisabledInstrumentsAreConfigured(): void
    {
        self::assertTrue(
            $this->agentFromConfigArray([ConfigKey::DISABLED_INSTRUMENTS => '["disabled functionality"]'])
                ->shouldInstrument('some functionality')
        );
    }

    public function testInstrumentationIsDisabledWhenDisabledInstrumentsAreConfigured(): void
    {
        self::assertFalse(
            $this->agentFromConfigArray([ConfigKey::DISABLED_INSTRUMENTS => '["disabled functionality"]'])
                ->shouldInstrument('disabled functionality')
        );
    }

    /** @throws Exception */
    public function testMetadataExceptionsAreLogged(): void
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
    public function testIgnoredAgentSequence(): void
    {
        $agent = $this->agentFromConfigArray([
            ConfigKey::MONITORING_ENABLED => true,
            ConfigKey::LOG_LEVEL => LogLevel::DEBUG,
        ]);
        $agent->ignore();

        // Start a Parent Controller Span
        $agent->startSpan('Controller/Test');

        // Tag Whole Request
        $agent->tagRequest('uri', 'example.com/foo/bar.php');

        // Start a Child Span
        $span = $agent->startSpan('SQL/Query');

        $agent->changeRequestUri('new request URI');

        // Tag the span
        if ($span !== null) {
            $span->tag('sql.query', 'select * from foo');
        }

        // Finish Child Span
        $agent->stopSpan();

        // Stop Controller Span
        $agent->stopSpan();

        self::assertFalse($agent->send());

        self::assertTrue($this->logger->hasDebugThatContains('Not sending payload, request has been ignored'));
    }

    public function testInstrumentedConsumerCodeBlockIsStillExecutedWithIgnoredRequest(): void
    {
        $agent = $this->agentFromConfigArray([ConfigKey::MONITORING_ENABLED => true]);
        $agent->ignore();

        $hasRun = false;

        $agent->instrument(
            'Type',
            'Name',
            static function () use (&$hasRun): void {
                $hasRun = true;
            }
        );

        self::assertTrue($hasRun, 'Callable passed to $agent->instrument was not executed');
    }

    /** @throws Exception */
    public function testRequestIsResetAfterCallingSend(): void
    {
        $agent = $this->agentFromConfigArray([
            ConfigKey::APPLICATION_NAME => 'My test app',
            ConfigKey::APPLICATION_KEY => uniqid('applicationKey', true),
            ConfigKey::MONITORING_ENABLED => true,
            ConfigKey::CORE_AGENT_DOWNLOAD_ENABLED => false,
            ConfigKey::CORE_AGENT_LAUNCH_ENABLED => false,
        ]);

        $requestBeforeSend = $this->requestFromAgent($agent);

        $this->connector->method('connected')->willReturn(true);
        $this->connector->expects(self::exactly(3))->method('sendCommand');

        self::assertTrue($agent->send());

        self::assertNotSame($requestBeforeSend, $this->requestFromAgent($agent));
    }

    public function testRegisterEventIsOnlySentOnceWhenSendingTwoRequestsWithSameAgent(): void
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

    public function testRequestIsResetAfterStartingANewRequest(): void
    {
        $agent = $this->agentFromConfigArray([
            ConfigKey::APPLICATION_NAME => 'My test app',
            ConfigKey::APPLICATION_KEY => uniqid('applicationKey', true),
            ConfigKey::MONITORING_ENABLED => true,
        ]);

        $requestBeforeReset = $this->requestFromAgent($agent);

        $agent->startNewRequest();

        self::assertNotSame($requestBeforeReset, $this->requestFromAgent($agent));
    }

    public function testAgentLogsWarningWhenFailingToConnectToSocket(): void
    {
        $agent = Agent::fromConfig(
            Config::fromArray([
                ConfigKey::APPLICATION_NAME => 'My test app',
                ConfigKey::APPLICATION_KEY => uniqid('applicationKey', true),
                ConfigKey::MONITORING_ENABLED => true,
                ConfigKey::CORE_AGENT_SOCKET_PATH => '/socket/path/should/not/exist',
                ConfigKey::CORE_AGENT_DOWNLOAD_ENABLED => false,
                ConfigKey::CORE_AGENT_LAUNCH_ENABLED => false,
            ]),
            $this->logger,
            new DevNullCache()
        );
        $agent->connect();

        self::assertTrue($this->logger->hasWarningThatContains(
            'Failed to connect to socket on address "/socket/path/should/not/exist"'
        ));
    }

    public function testAgentLogsDebugWhenConnectedToSocket(): void
    {
        $this->connector
            ->expects(self::once())
            ->method('connected')
            ->willReturn(false);

        $this->connector
            ->expects(self::once())
            ->method('connect');

        $agent = $this->agentFromConfigArray([
            ConfigKey::APPLICATION_NAME => 'My test app',
            ConfigKey::APPLICATION_KEY => uniqid('applicationKey', true),
            ConfigKey::MONITORING_ENABLED => true,
            ConfigKey::CORE_AGENT_DOWNLOAD_ENABLED => false,
            ConfigKey::CORE_AGENT_LAUNCH_ENABLED => false,
            ConfigKey::LOG_LEVEL => LogLevel::DEBUG,
        ]);

        $agent->connect();

        self::assertTrue($this->logger->hasDebugThatContains('Connected to connector.'));
    }

    public function testAgentLogsDebugWhenAlreadyConnectedToSocket(): void
    {
        $this->connector
            ->expects(self::once())
            ->method('connected')
            ->willReturn(true);

        $this->connector
            ->expects(self::never())
            ->method('connect');

        $agent = $this->agentFromConfigArray([
            ConfigKey::APPLICATION_NAME => 'My test app',
            ConfigKey::APPLICATION_KEY => uniqid('applicationKey', true),
            ConfigKey::MONITORING_ENABLED => true,
            ConfigKey::CORE_AGENT_DOWNLOAD_ENABLED => false,
            ConfigKey::LOG_LEVEL => LogLevel::DEBUG,
        ]);

        $agent->connect();

        self::assertTrue($this->logger->hasDebugThatContains('Scout Core Agent Connected'));
    }

    public function testRequestUriCanBeChanged(): void
    {
        $agent = $this->agentFromConfigArray([
            ConfigKey::APPLICATION_NAME => 'My test app',
            ConfigKey::APPLICATION_KEY => uniqid('applicationKey', true),
            ConfigKey::MONITORING_ENABLED => true,
            ConfigKey::LOG_LEVEL => LogLevel::NOTICE,
        ]);

        $requestUri = uniqid('requestUri', true);

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
            ->with(self::callback(static function (Request $request) use ($requestUri) {
                $serialisedRequest = json_encode($request);

                self::assertStringContainsString(sprintf('"tag":"path","value":"%s"', $requestUri), $serialisedRequest);

                return true;
            }))
            ->willReturn('{"Request":"Success"}');

        $agent->changeRequestUri($requestUri);

        self::assertTrue($agent->send());
    }

    public function testDisablingMonitoringDoesNotSendPayload(): void
    {
        $agent = $this->agentFromConfigArray([
            ConfigKey::APPLICATION_NAME => 'My test app',
            ConfigKey::APPLICATION_KEY => uniqid('applicationKey', true),
            ConfigKey::MONITORING_ENABLED => false,
            ConfigKey::LOG_LEVEL => LogLevel::DEBUG,
        ]);

        $this->connector->expects(self::never())->method('connected')->willReturn(true);

        $this->connector->expects(self::never())
            ->method('sendCommand');

        self::assertFalse($agent->send());

        self::assertTrue($this->logger->hasDebugThatContains('Not sending payload, monitoring is not enabled'));
    }

    public function testSendingRequestAttemptsToConnectIfNotAlreadyConnected(): void
    {
        $agent = $this->agentFromConfigArray([
            ConfigKey::APPLICATION_NAME => 'My test app',
            ConfigKey::APPLICATION_KEY => uniqid('applicationKey', true),
            ConfigKey::MONITORING_ENABLED => true,
            ConfigKey::LOG_LEVEL => LogLevel::DEBUG,
        ]);

        $this->connector->expects(self::once())
            ->method('connected')
            ->willReturn(false);

        $this->connector->expects(self::once())
            ->method('connect');

        $this->connector->expects(self::at(2))
            ->method('sendCommand')
            ->with(self::isInstanceOf(RegisterMessage::class))
            ->willReturn('{"Register":"Success"}');
        $this->connector->expects(self::at(3))
            ->method('sendCommand')
            ->with(self::isInstanceOf(Metadata::class))
            ->willReturn('{"Metadata":"Success"}');
        $this->connector->expects(self::at(4))
            ->method('sendCommand')
            ->with(self::isInstanceOf(Request::class))
            ->willReturn('{"Request":"Success"}');

        self::assertTrue($agent->send());

        self::assertTrue($this->logger->hasDebugThatContains('Connected to connector whilst sending'));
    }

    public function testFailureToConnectWhilstSendingIsLoggedAsAnError(): void
    {
        $agent = $this->agentFromConfigArray([
            ConfigKey::APPLICATION_NAME => 'My test app',
            ConfigKey::APPLICATION_KEY => uniqid('applicationKey', true),
            ConfigKey::MONITORING_ENABLED => true,
        ]);

        $this->connector->expects(self::once())
            ->method('connected')
            ->willReturn(false);

        $this->connector->expects(self::once())
            ->method('connect')
            ->willThrowException(new FailedToConnect('Uh oh, failed to reticulate the splines'));

        $this->connector->expects(self::never())
            ->method('sendCommand');

        self::assertFalse($agent->send());

        self::assertTrue($this->logger->hasErrorThatContains('Uh oh, failed to reticulate the splines'));
    }

    public function testNotConnectedExceptionIsCaughtWhilstSending(): void
    {
        $agent = $this->agentFromConfigArray([
            ConfigKey::APPLICATION_NAME => 'My test app',
            ConfigKey::APPLICATION_KEY => uniqid('applicationKey', true),
            ConfigKey::MONITORING_ENABLED => true,
        ]);

        $this->connector->expects(self::once())
            ->method('connected')
            ->willReturn(true);

        // This scenario is very unlikely, but ensure it's caught anyway...
        $this->connector->expects(self::once())
            ->method('sendCommand')
            ->willThrowException(new NotConnected('Lost connectivity whilst reticulating splines'));

        self::assertFalse($agent->send());

        self::assertTrue($this->logger->hasErrorThatContains('Lost connectivity whilst reticulating splines'));
    }

    public function testFailureToSendCommandExceptionIsCaughtWhilstSending(): void
    {
        $agent = $this->agentFromConfigArray([
            ConfigKey::APPLICATION_NAME => 'My test app',
            ConfigKey::APPLICATION_KEY => uniqid('applicationKey', true),
            ConfigKey::MONITORING_ENABLED => true,
        ]);

        $this->connector->expects(self::once())
            ->method('connected')
            ->willReturn(true);

        $this->connector->expects(self::once())
            ->method('sendCommand')
            ->willThrowException(new FailedToSendCommand(LogLevel::ERROR, 'Splines did not reticulate to send the message'));

        self::assertFalse($agent->send());

        self::assertTrue($this->logger->hasErrorThatContains('Splines did not reticulate to send the message'));
    }

    public function testFailureToSendCommandExceptionIsCaughtWhilstSendingWithNoticeLogLevel(): void
    {
        $agent = $this->agentFromConfigArray([
            ConfigKey::APPLICATION_NAME => 'My test app',
            ConfigKey::APPLICATION_KEY => uniqid('applicationKey', true),
            ConfigKey::MONITORING_ENABLED => true,
            ConfigKey::LOG_LEVEL => LogLevel::DEBUG,
        ]);

        $this->connector->expects(self::once())
            ->method('connected')
            ->willReturn(true);

        $this->connector->expects(self::once())
            ->method('sendCommand')
            ->willThrowException(new FailedToSendCommand(LogLevel::NOTICE, 'Splines did not reticulate to send the message'));

        self::assertFalse($agent->send());

        self::assertTrue($this->logger->hasNoticeThatContains('Splines did not reticulate to send the message'));
    }

    public function testOlderVersionsOfExtensionIsNotedInLogs(): void
    {
        $agent = $this->agentFromConfigArray([
            ConfigKey::APPLICATION_NAME => 'My test app',
            ConfigKey::APPLICATION_KEY => uniqid('applicationKey', true),
            ConfigKey::MONITORING_ENABLED => true,
            ConfigKey::CORE_AGENT_DOWNLOAD_ENABLED => false,
            ConfigKey::CORE_AGENT_LAUNCH_ENABLED => false,
            ConfigKey::LOG_LEVEL => LogLevel::DEBUG,
        ]);

        $this->phpExtension
            ->method('version')
            ->willReturn(Version::fromString('0.0.1'));

        $agent->connect();

        self::assertTrue($this->logger->hasInfoThatContains(
            'scoutapm PHP extension is currently 0.0.1, which is older than the minimum recommended version'
        ));
    }

    public function testNewerVersionsOfExtensionIsNotLogged(): void
    {
        $agent = $this->agentFromConfigArray([
            ConfigKey::APPLICATION_NAME => 'My test app',
            ConfigKey::APPLICATION_KEY => uniqid('applicationKey', true),
            ConfigKey::MONITORING_ENABLED => true,
            ConfigKey::CORE_AGENT_DOWNLOAD_ENABLED => false,
            ConfigKey::CORE_AGENT_LAUNCH_ENABLED => false,
        ]);

        $this->phpExtension
            ->method('version')
            ->willReturn(Version::fromString('100.0.0'));

        $agent->connect();

        self::assertFalse($this->logger->hasInfoThatContains('scoutapm PHP extension is currently'));
    }

    public function testCoreAgentPayloadAndResponseAreLoggedWhenEnabled(): void
    {
        $agent = $this->agentFromConfigArray([
            ConfigKey::APPLICATION_NAME => 'My test app',
            ConfigKey::APPLICATION_KEY => uniqid('applicationKey', true),
            ConfigKey::MONITORING_ENABLED => true,
            ConfigKey::CORE_AGENT_DOWNLOAD_ENABLED => false,
            ConfigKey::CORE_AGENT_LAUNCH_ENABLED => false,
            ConfigKey::LOG_PAYLOAD_CONTENT => true,
            ConfigKey::LOG_LEVEL => LogLevel::DEBUG,
        ]);

        $this->connector->expects(self::at(2))
            ->method('sendCommand')
            ->with(self::isInstanceOf(RegisterMessage::class))
            ->willReturn('{"Register":"Success"}');
        $this->connector->expects(self::at(3))
            ->method('sendCommand')
            ->with(self::isInstanceOf(Metadata::class))
            ->willReturn('{"Metadata":"Success"}');
        $this->connector->expects(self::at(4))
            ->method('sendCommand')
            ->with(self::isInstanceOf(Request::class))
            ->willReturn('{"Request":"Success"}');

        $agent->instrument(
            'Test',
            'Foo',
            static function (): void {
            }
        );
        $agent->instrument(
            'Test',
            'Bar',
            static function (): void {
            }
        );
        $agent->send();

        self::assertTrue($this->logger->hasDebugThatContains('Sending metrics from 2 collected spans. Payload: {'));
        self::assertTrue($this->logger->hasDebugThatContains('Sent whole payload successfully to core agent. Response: {"Request":"Success"}'));
    }

    /** @throws Exception */
    public function testNumberOfSpansIsLimitedAndLogged(): void
    {
        $agent = $this->agentFromConfigArray([
            ConfigKey::APPLICATION_NAME => 'My test app',
            ConfigKey::APPLICATION_KEY => uniqid('applicationKey', true),
            ConfigKey::MONITORING_ENABLED => true,
            ConfigKey::LOG_LEVEL => LogLevel::DEBUG,
        ]);

        // Even if we randomise the number of spans over the limit, the number of spans actually sent should remain fixed
        $maxSpansToStart = random_int(self::EXPECTED_SPAN_LIMIT, self::EXPECTED_SPAN_LIMIT + 100);

        for ($i = 0; $i <= $maxSpansToStart; $i++) {
            $agent->startSpan(sprintf('span %d', $i));
            $agent->stopSpan();
        }

        $this->connector->method('connected')->willReturn(true);

        /** @noinspection PhpParamsInspection */
        $this->connector->expects(self::at(1))
            ->method('sendCommand')
            ->with(self::isInstanceOf(RegisterMessage::class))
            ->willReturn('{"Register":"Success"}');
        /** @noinspection PhpParamsInspection */
        $this->connector->expects(self::at(2))
            ->method('sendCommand')
            ->with(self::isInstanceOf(Metadata::class))
            ->willReturn('{"Metadata":"Success"}');
        /** @noinspection PhpParamsInspection */
        $this->connector->expects(self::at(3))
            ->method('sendCommand')
            ->with(self::callback(static function (Request $request): bool {
                TestHelper::assertUnserializedCommandContainsPayload(
                    'BatchCommand',
                    [
                        'commands' => static function (array $commands): bool {
                            // StartRequest
                            // span limit * 2 for Start/StopSpans
                            // TagRequest for limit
                            // Tag for memory,
                            self::assertCount((self::EXPECTED_SPAN_LIMIT * 2) + 5, $commands);
                            TestHelper::assertUnserializedCommandContainsPayload('StartRequest', [], $commands[0], null);
                            TestHelper::assertUnserializedCommandContainsPayload('TagRequest', ['tag' => 'scout.reached_span_cap', 'value' => true], $commands[(self::EXPECTED_SPAN_LIMIT * 2) + 1], null);
                            TestHelper::assertUnserializedCommandContainsPayload('TagRequest', ['tag' => 'memory_delta'], $commands[(self::EXPECTED_SPAN_LIMIT * 2) + 2], null);
                            TestHelper::assertUnserializedCommandContainsPayload('TagRequest', ['tag' => 'path'], $commands[(self::EXPECTED_SPAN_LIMIT * 2) + 3], null);
                            TestHelper::assertUnserializedCommandContainsPayload('FinishRequest', [], $commands[(self::EXPECTED_SPAN_LIMIT * 2) + 4], null);

                            return true;
                        },
                    ],
                    $request->jsonSerialize(),
                    null
                );

                return true;
            }))
            ->willReturn('{"Request":"Success"}');

        self::assertTrue($agent->send());

        self::assertTrue($this->logger->hasInfoThatContains(sprintf('Span limit of %d has been reached trying to start span for "span %d"', self::EXPECTED_SPAN_LIMIT, self::EXPECTED_SPAN_LIMIT)));
    }

    public function testMetadataIsNotSentIfCached(): void
    {
        $agent = Agent::fromConfig(
            Config::fromArray([
                ConfigKey::APPLICATION_NAME => 'My test app',
                ConfigKey::APPLICATION_KEY => uniqid('applicationKey', true),
                ConfigKey::MONITORING_ENABLED => true,
            ]),
            $this->logger,
            new ArrayCachePool(),
            $this->connector,
            $this->phpExtension
        );

        $this->connector->expects(self::at(0))
            ->method('connected')
            ->willReturn(true);

        /** @noinspection PhpParamsInspection */
        $this->connector->expects(self::at(1))
            ->method('sendCommand')
            ->with(self::isInstanceOf(RegisterMessage::class))
            ->willReturn('{"Register":"Success"}');
        /** @noinspection PhpParamsInspection */
        $this->connector->expects(self::at(2))
            ->method('sendCommand')
            ->with(self::isInstanceOf(Metadata::class))
            ->willReturn('{"Metadata":"Success"}');
        /** @noinspection PhpParamsInspection */
        $this->connector->expects(self::at(3))
            ->method('sendCommand')
            ->with(self::isInstanceOf(Request::class))
            ->willReturn('{"Request":"Success"}');
        $this->connector->expects(self::at(4))
            ->method('connected')
            ->willReturn(true);
        /** @noinspection PhpParamsInspection */
        $this->connector->expects(self::at(5))
            ->method('sendCommand')
            ->with(self::isInstanceOf(Request::class))
            ->willReturn('{"Request":"Success"}');

        $agent->startSpan('a');
        $agent->stopSpan();

        $agent->send();

        $agent->startSpan('b');
        $agent->stopSpan();

        $agent->send();
    }
}
