<?php

declare(strict_types=1);

namespace Scoutapm\IntegrationTests;

use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use Scoutapm\Agent;
use Scoutapm\Config;
use Scoutapm\Connector\SocketConnector;
use function getenv;
use function gethostname;
use function is_callable;
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

        $connector = new MessageCapturingConnectorDelegator(new SocketConnector($config->get('socket_path')));

        $agent = Agent::fromConfig($config, $this->logger, $connector);

        $agent->connect();

        $agent->webTransaction('Yay', static function () use ($agent) : void {
            $agent->instrument('test', 'foo', static function () : void {
                sleep(1);
            });
            $agent->instrument('test', 'foo2', static function () : void {
                sleep(1);
            });
            $agent->tagRequest('testtag', '1.23');
        });

        self::assertTrue($agent->send(), 'Failed to send messages. ' . $this->formatCapturedLogMessages());

        $unserialized = json_decode(json_encode($connector->sentMessages), true);

        $this->assertUnserializedCommandContainsPayload(
            'Register',
            [
                'app' => 'Agent Integration Test',
                'key' => $scoutApmKey,
                'language' => 'php',
                'api_version' => '1.0',
            ],
            reset($unserialized)
        );
        $this->assertUnserializedCommandContainsPayload(
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
            next($unserialized)
        );

        $batchCommand = next($unserialized);
        $this->assertUnserializedCommandContainsPayload(
            'BatchCommand',
            [
                'commands' => function (array $commands) : bool {
                    $this->assertUnserializedCommandContainsPayload('StartRequest', [], reset($commands));

                    $this->assertUnserializedCommandContainsPayload('StartSpan', ['operation' => 'test/foo'], next($commands));
                    $this->assertUnserializedCommandContainsPayload('TagSpan', ['tag' => 'stack'], next($commands));
                    $this->assertUnserializedCommandContainsPayload('StopSpan', [], next($commands));

                    $this->assertUnserializedCommandContainsPayload('StartSpan', ['operation' => 'test/foo2'], next($commands));
                    $this->assertUnserializedCommandContainsPayload('TagSpan', ['tag' => 'stack'], next($commands));
                    $this->assertUnserializedCommandContainsPayload('StopSpan', [], next($commands));

                    $this->assertUnserializedCommandContainsPayload('TagRequest', ['tag' => 'testtag', 'value' => '1.23'], next($commands));

                    $this->assertUnserializedCommandContainsPayload('StartSpan', ['operation' => 'Controller/Yay'], next($commands));
                    $this->assertUnserializedCommandContainsPayload('TagSpan', ['tag' => 'stack'], next($commands));
                    $this->assertUnserializedCommandContainsPayload('StopSpan', [], next($commands));

                    $this->assertUnserializedCommandContainsPayload('FinishRequest', [], next($commands));

                    return true;
                },
            ],
            $batchCommand
        );
    }

    /**
     * @param string[]|callable[]|array<string, (string|callable)>        $keysAndValuesToExpect
     * @param mixed[][]|array<string, array<string, (string|null|array)>> $actualCommand
     */
    private function assertUnserializedCommandContainsPayload(
        string $expectedCommand,
        array $keysAndValuesToExpect,
        array $actualCommand
    ) : void {
        self::assertArrayHasKey($expectedCommand, $actualCommand);
        $commandPayload = $actualCommand[$expectedCommand];

        foreach ($keysAndValuesToExpect as $expectedKey => $expectedValue) {
            self::assertArrayHasKey($expectedKey, $commandPayload);

            if (is_callable($expectedValue)) {
                self::assertTrue($expectedValue($commandPayload[$expectedKey]));
                continue;
            }

            self::assertSame($expectedValue, $commandPayload[$expectedKey]);
        }
    }
}
