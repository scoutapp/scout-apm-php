<?php

declare(strict_types=1);

namespace Scoutapm\IntegrationTests;

use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use Scoutapm\Agent;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;
use Scoutapm\Connector\SocketConnector;
use Scoutapm\Extension\PotentiallyAvailableExtensionCapabilities;
use Scoutapm\Helper\Timer;
use function file_get_contents;
use function getenv;
use function gethostname;
use function json_decode;
use function json_encode;
use function next;
use function reset;
use function sleep;
use function sprintf;

/** @coversNothing */
final class AgentTest extends TestCase
{
    /** @var TestLogger */
    private $logger;

    public function setUp() : void
    {
        parent::setUp();

        $this->logger = new TestLogger();
    }

    private function formatCapturedLogMessages() : string
    {
        $return = "Log messages:\n";

        foreach ($this->logger->records as $logMessage) {
            $return .= sprintf("[%s] %s\n", $logMessage['level'], $logMessage['message']);
        }

        return $return;
    }

    /** @throws Exception */
    public function testLoggingIsSent() : void
    {
        // Note, env var name is intentionally inconsistent (i.e. not `SCOUT_KEY`) as we only want to affect this test
        $scoutApmKey = getenv('SCOUT_APM_KEY');

        if ($scoutApmKey === false) {
            self::markTestSkipped('Set the environment variable SCOUT_APM_KEY to enable this test.');

            return;
        }

        $config = Config::fromArray([
            'name' => 'Agent Integration Test',
            'key' => $scoutApmKey,
            'monitor' => true,
        ]);

        $connector = new MessageCapturingConnectorDelegator(
            new SocketConnector($config->get(ConfigKey::CORE_AGENT_SOCKET_PATH), true)
        );

        $_SERVER['REQUEST_URI'] = '/fake-path';

        $agent = Agent::fromConfig($config, $this->logger, null, $connector);

        $agent->connect();

        (new PotentiallyAvailableExtensionCapabilities())->clearRecordedCalls();

        $agent->webTransaction('Yay', static function () use ($agent) : void {
            file_get_contents(__FILE__);
            $agent->instrument('Test', 'foo', static function () use ($agent) : void {
                file_get_contents(__FILE__);
                sleep(1);
                $agent->instrument('Test', 'bar', static function () : void {
                    file_get_contents(__FILE__);
                    sleep(1);
                });
            });
            file_get_contents(__FILE__);
            $agent->tagRequest('testtag', '1.23');
            $agent->instrument('DB', 'test', static function () : void {
            });
        });
        $agent->instrument('Test', 'qux', static function () : void {
        });

        self::assertTrue($agent->send(), 'Failed to send messages. ' . $this->formatCapturedLogMessages());

        $unserialized = json_decode(json_encode($connector->sentMessages), true);

        TestHelper::assertUnserializedCommandContainsPayload(
            'Register',
            [
                'app' => 'Agent Integration Test',
                'key' => $scoutApmKey,
                'language' => 'php',
                'api_version' => '1.0',
            ],
            reset($unserialized),
            null
        );
        TestHelper::assertUnserializedCommandContainsPayload(
            'ApplicationEvent',
            [
                'event_type' => 'scout.metadata',
                'source' => 'php',
                'event_value' => static function (array $data) : bool {
                    self::assertSame('php', $data['language']);
                    self::assertSame(gethostname(), $data['hostname']);

                    return true;
                },
            ],
            next($unserialized),
            null
        );

        $batchCommand = next($unserialized);
        TestHelper::assertUnserializedCommandContainsPayload(
            'BatchCommand',
            [
                'commands' => function (array $commands) : bool {
                    $requestId = TestHelper::assertUnserializedCommandContainsPayload(
                        'StartRequest',
                        [
                            'timestamp' => [$this, 'assertValidTimestamp'],
                        ],
                        reset($commands),
                        'request_id'
                    );

                    $controllerSpanId = TestHelper::assertUnserializedCommandContainsPayload('StartSpan', ['operation' => 'Controller/Yay'], next($commands), 'span_id');

                    if (TestHelper::scoutApmExtensionAvailable()) {
                        $fileGetContentsSpanId = TestHelper::assertUnserializedCommandContainsPayload('StartSpan', ['operation' => 'file_get_contents', 'parent_id' => $controllerSpanId], next($commands), 'span_id');
                        TestHelper::assertUnserializedCommandContainsPayload('TagSpan', ['span_id' => $fileGetContentsSpanId, 'tag' => 'args', 'value' => ['url' => __FILE__]], next($commands), null);
                        TestHelper::assertUnserializedCommandContainsPayload('StopSpan', ['span_id' => $fileGetContentsSpanId], next($commands), null);
                    }

                    $fooSpanId = TestHelper::assertUnserializedCommandContainsPayload('StartSpan', ['operation' => 'Test/foo'], next($commands), 'span_id');

                    if (TestHelper::scoutApmExtensionAvailable()) {
                        $fileGetContentsSpanId = TestHelper::assertUnserializedCommandContainsPayload('StartSpan', ['operation' => 'file_get_contents', 'parent_id' => $fooSpanId], next($commands), 'span_id');
                        TestHelper::assertUnserializedCommandContainsPayload('TagSpan', ['span_id' => $fileGetContentsSpanId, 'tag' => 'args', 'value' => ['url' => __FILE__]], next($commands), null);
                        TestHelper::assertUnserializedCommandContainsPayload('StopSpan', ['span_id' => $fileGetContentsSpanId], next($commands), null);
                    }

                    $barSpanId = TestHelper::assertUnserializedCommandContainsPayload('StartSpan', ['operation' => 'Test/bar'], next($commands), 'span_id');

                    if (TestHelper::scoutApmExtensionAvailable()) {
                        $fileGetContentsSpanId = TestHelper::assertUnserializedCommandContainsPayload('StartSpan', ['operation' => 'file_get_contents', 'parent_id' => $barSpanId], next($commands), 'span_id');
                        TestHelper::assertUnserializedCommandContainsPayload('TagSpan', ['span_id' => $fileGetContentsSpanId, 'tag' => 'args', 'value' => ['url' => __FILE__]], next($commands), null);
                        TestHelper::assertUnserializedCommandContainsPayload('StopSpan', ['span_id' => $fileGetContentsSpanId], next($commands), null);
                    }

                    TestHelper::assertUnserializedCommandContainsPayload('TagSpan', ['tag' => 'stack', 'span_id' => $barSpanId], next($commands), null);
                    TestHelper::assertUnserializedCommandContainsPayload('StopSpan', ['span_id' => $barSpanId], next($commands), null);

                    TestHelper::assertUnserializedCommandContainsPayload('TagSpan', ['tag' => 'stack', 'span_id' => $fooSpanId], next($commands), null);
                    TestHelper::assertUnserializedCommandContainsPayload('StopSpan', ['span_id' => $fooSpanId], next($commands), null);

                    if (TestHelper::scoutApmExtensionAvailable()) {
                        $fileGetContentsSpanId = TestHelper::assertUnserializedCommandContainsPayload('StartSpan', ['operation' => 'file_get_contents', 'parent_id' => $controllerSpanId], next($commands), 'span_id');
                        TestHelper::assertUnserializedCommandContainsPayload('TagSpan', ['span_id' => $fileGetContentsSpanId, 'tag' => 'args', 'value' => ['url' => __FILE__]], next($commands), null);
                        TestHelper::assertUnserializedCommandContainsPayload('StopSpan', ['span_id' => $fileGetContentsSpanId], next($commands), null);
                    }

                    $dbSpanId = TestHelper::assertUnserializedCommandContainsPayload('StartSpan', ['operation' => 'DB/test'], next($commands), 'span_id');
                    TestHelper::assertUnserializedCommandContainsPayload('StopSpan', ['span_id' => $dbSpanId], next($commands), null);

                    TestHelper::assertUnserializedCommandContainsPayload('StopSpan', ['span_id' => $controllerSpanId], next($commands), null);

                    TestHelper::assertUnserializedCommandContainsPayload('TagRequest', ['tag' => 'testtag', 'value' => '1.23', 'request_id' => $requestId], next($commands), null);

                    $quxSpanId = TestHelper::assertUnserializedCommandContainsPayload('StartSpan', ['operation' => 'Test/qux'], next($commands), 'span_id');
                    TestHelper::assertUnserializedCommandContainsPayload('StopSpan', ['span_id' => $quxSpanId], next($commands), null);

                    TestHelper::assertUnserializedCommandContainsPayload('TagRequest', ['tag' => 'memory_delta', 'value' => [$this, 'assertValidMemoryUsage'], 'request_id' => $requestId], next($commands), null);
                    TestHelper::assertUnserializedCommandContainsPayload('TagRequest', ['tag' => 'path', 'value' => '/fake-path', 'request_id' => $requestId], next($commands), null);

                    TestHelper::assertUnserializedCommandContainsPayload(
                        'FinishRequest',
                        [
                            'request_id' => $requestId,
                            'timestamp' => [$this, 'assertValidTimestamp'],
                        ],
                        next($commands),
                        null
                    );

                    return true;
                },
            ],
            $batchCommand,
            null
        );
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    /** @throws Exception */
    // phpcs:disable SlevomatCodingStandard.Classes.UnusedPrivateElements.UnusedMethod
    private function assertValidTimestamp(?string $timestamp) : bool
    {
        self::assertNotNull($timestamp, 'Expected a non-null timestamp, but the timestamp was null');
        self::assertSame($timestamp, (new DateTimeImmutable($timestamp))->format(Timer::FORMAT_FOR_CORE_AGENT));

        return true;
    }

    /** @noinspection PhpUnusedPrivateMethodInspection */
    private function assertValidMemoryUsage(?float $memoryUsageInMb) : bool
    {
        self::assertIsFloat($memoryUsageInMb, 'Expected an float memory usage, but was it was null');
        self::assertGreaterThan(0, $memoryUsageInMb, 'Memory usage should be greater than zero');

        return true;
    }
    // phpcs:enable
}
