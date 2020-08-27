<?php

declare(strict_types=1);

namespace Scoutapm\IntegrationTests;

use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use Scoutapm\Agent;
use Scoutapm\Config;
use Scoutapm\Connector\ConnectionAddress;
use Scoutapm\Connector\SocketConnector;
use Scoutapm\Events\Span\Span;
use Scoutapm\Extension\PotentiallyAvailableExtensionCapabilities;
use function file_get_contents;
use function fopen;
use function function_exists;
use function getenv;
use function gethostname;
use function meminfo_dump;
use function memory_get_usage;
use function next;
use function reset;
use function sleep;
use function sprintf;
use function str_repeat;

/** @coversNothing */
final class AgentTest extends TestCase
{
    /** @var TestLogger */
    private $logger;
    /** @var MessageCapturingConnectorDelegator */
    private $connector;
    /** @var Agent */
    private $agent;
    /** @var string */
    private $scoutApmKey;

    public function setUp() : void
    {
        parent::setUp();

        $this->logger = new TestLogger();

        // Note, env var name is intentionally inconsistent (i.e. not `SCOUT_KEY`) as we only want to affect this test
        $this->scoutApmKey = getenv('SCOUT_APM_KEY');

        if ($this->scoutApmKey === false || $this->scoutApmKey === '') {
            self::markTestSkipped('Set the environment variable SCOUT_APM_KEY to enable this test.');

            return;
        }

        $config = Config::fromArray([
            'name' => 'Agent Integration Test',
            'key' => $this->scoutApmKey,
            'monitor' => true,
        ]);

        $this->connector = new MessageCapturingConnectorDelegator(
            new SocketConnector(ConnectionAddress::fromConfig($config), true)
        );

        $_SERVER['REQUEST_URI'] = '/fake-path';

        $this->agent = Agent::fromConfig($config, $this->logger, null, $this->connector);
        $this->agent->connect();

        (new PotentiallyAvailableExtensionCapabilities())->clearRecordedCalls();
    }

    private function formatCapturedLogMessages() : string
    {
        $return = "Log messages:\n";

        foreach ($this->logger->records as $logMessage) {
            $return .= sprintf("[%s] %s\n", $logMessage['level'], $logMessage['message']);
        }

        return $return;
    }

    public function testForMemoryLeaksWhenHandlingJobQueues() : void
    {
        $tagSize = 500000;

        $startingMemory = memory_get_usage();
        for ($i = 1; $i <= 500; $i++) {
            $this->agent->startNewRequest();
            $span = $this->agent->startSpan(sprintf(
                '%s/%s%d',
                Span::INSTRUMENT_JOB,
                'Test Job #',
                $i
            ));

            $span->tag('something', str_repeat('a', $tagSize));

            $this->agent->stopSpan();

            $this->agent->connect();
            $this->agent->send();
        }

        // Logging can affect memory usage since the TestLogger persists the messages in memory
        $this->logger->records        = [];
        $this->logger->recordsByLevel = [];

        // MessageCapturingConnectorDelegator also persists the sent messages in memory for tests, so free that up
        $this->connector->sentMessages = [];

        /**
         * Install https://github.com/BitOne/php-meminfo to get memory analysis here to identify where memory is
         * allocated, then use `bin/analyzer summary /tmp/my_dump_file.json` to show table.
         */
        if (function_exists('meminfo_dump')) {
            meminfo_dump(fopen('/tmp/my_dump_file.json', 'w'));
        }

        // It is worth noting that other things are collecting information here too (e.g. xdebug, phpunit), so we can
        // expect memory to increase regardless.
        self::assertLessThan($tagSize * 2, memory_get_usage() - $startingMemory);
    }

    /** @throws Exception */
    public function testLoggingIsSent() : void
    {
        $this->agent->webTransaction('Yay', function () : void {
            file_get_contents(__FILE__);
            $this->agent->instrument('Test', 'foo', function () : void {
                file_get_contents(__FILE__);
                sleep(1);
                $this->agent->instrument('Test', 'bar', static function () : void {
                    file_get_contents(__FILE__);
                    sleep(1);
                });
            });
            file_get_contents(__FILE__);
            $this->agent->tagRequest('testtag', '1.23');
            $this->agent->instrument('DB', 'test', static function () : void {
            });
        });
        $this->agent->instrument('Test', 'qux', static function () : void {
        });

        self::assertTrue($this->agent->send(), 'Failed to send messages. ' . $this->formatCapturedLogMessages());

        $unserialized = $this->connector->sentMessages;

        TestHelper::assertUnserializedCommandContainsPayload(
            'Register',
            [
                'app' => 'Agent Integration Test',
                'key' => $this->scoutApmKey,
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
                'commands' => static function (array $commands) : bool {
                    $requestId = TestHelper::assertUnserializedCommandContainsPayload(
                        'StartRequest',
                        [
                            'timestamp' => [TestHelper::class, 'assertValidTimestamp'],
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

                    TestHelper::assertUnserializedCommandContainsPayload('TagRequest', ['tag' => 'memory_delta', 'value' => [TestHelper::class, 'assertValidMemoryUsage'], 'request_id' => $requestId], next($commands), null);
                    TestHelper::assertUnserializedCommandContainsPayload('TagRequest', ['tag' => 'path', 'value' => '/fake-path', 'request_id' => $requestId], next($commands), null);

                    TestHelper::assertUnserializedCommandContainsPayload(
                        'FinishRequest',
                        [
                            'request_id' => $requestId,
                            'timestamp' => [TestHelper::class, 'assertValidTimestamp'],
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
}
