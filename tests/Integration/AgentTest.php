<?php

declare(strict_types=1);

namespace Scoutapm\IntegrationTests;

use PHPUnit\Framework\TestCase;
use Scoutapm\Agent;
use Scoutapm\Config;
use Scoutapm\Connector\Connector;
use Scoutapm\Events\Request\Request;
use function getenv;
use function json_decode;
use function json_encode;

/** @coversNothing */
final class AgentTest extends TestCase
{
    public function testLoggingIsSent() : void
    {
        $scoutApmKey = getenv('SCOUT_APM_KEY');

        if ($scoutApmKey === false) {
            self::markTestSkipped('Set the environment variable SCOUT_APM_KEY to enable this test.');

            return;
        }

        $connector = new class implements Connector {
            /** @var Request */
            public $sentRequest;

            public function connect() : void
            {
            }

            public function connected() : bool
            {
                return true;
            }

            public function sendRequest(Request $request) : bool
            {
                $this->sentRequest = $request;

                return true;
            }

            public function shutdown() : void
            {
            }
        };

        $config = new Config();
        $config->set('name', 'Agent integration test');
        $config->set('key', $scoutApmKey);
        $config->set('monitor', true);

        $agent = Agent::fromConfig($config, null, $connector);

        $agent->connect();

        $agent->webTransaction('Yay', static function () use ($agent) : void {
            $agent->instrument('test', 'foo', static function () : void {
            });
            $agent->instrument('test', 'foo2', static function () : void {
            });
            $agent->tagRequest('testtag', '1.23');
        });

        self::assertTrue($agent->send());

        // @todo check the format of this matches up with expectations
        self::markTestIncomplete(__METHOD__);
        self::assertEquals(
            [
                [
                    'StartRequest' => [],
                ],
                [
                    'StartSpan' => [],
                ],
                [
                    'StopSpan' => [],
                ],
                [
                    'StartSpan' => [],
                ],
                [
                    'StopSpan' => [],
                ],
                [
                    'RequestTag' => [],
                ],
                [
                    'StartSpan' => [],
                ],
                [
                    'StopSpan' => [],
                ],
                [
                    'FinishRequest' => [],
                ],
            ],
            json_decode(json_encode($connector->sentRequest), true)
        );

        // @todo perform more assertions - did we actually successfully send payload in the right format, etc.?
    }
}
