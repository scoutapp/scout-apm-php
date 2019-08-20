<?php

declare(strict_types=1);

namespace Scoutapm\IntegrationTests;

use PHPUnit\Framework\TestCase;
use Scoutapm\Agent;
use function getenv;
use function sleep;

final class AgentTest extends TestCase
{
    public function testLoggingIsSent() : void
    {
        $scoutApmKey = getenv('SCOUT_APM_KEY');

        if ($scoutApmKey === false) {
            self::markTestSkipped('Set the environment variable SCOUT_APM_KEY to enable this test.');

            return;
        }

        $agent = Agent::fromDefaults();

        $config = $agent->getConfig();

        $config->set('name', 'Agent integration test');
        $config->set('key', $scoutApmKey);
        $config->set('monitor', true);

        $agent->connect();

        // @todo currently have wait for agent to become available, not ideal, fix this...)
        sleep(1);

        $agent->webTransaction('Yay', static function () use ($agent) : void {
            $agent->instrument('test', 'foo', static function () : void {
            });
            $agent->instrument('test', 'foo2', static function () : void {
            });
            $agent->tagRequest('testtag', '1.23');
        });

        self::assertTrue($agent->send());

        // @todo perform more assertions - did we actually successfully send payload in the right format, etc.?
    }
}
