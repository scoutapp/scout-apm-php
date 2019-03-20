<?php
namespace Scoutapm\Tests;

use \Scoutapm\Agent;
use \PHPUnit\Framework\TestCase;
use Scoutapm\Exception\Timer\NotStartedException;

/**
* Test Case for @see \Scoutapm\Agent
*/
final class AgentTest extends TestCase {

    /**
    * @covers \Scoutapm\Agent::__construct
    * @covers \Scoutapm\Agent::startRequest
    * @covers \Scoutapm\Agent::stopRequest
    * @covers \Scoutapm\Agent::getRequest
    */
    public function testStartAndStopARequest() {
        $agent = new Agent([ 'appName' => 'phpunit_1', 'key' => '1234' ]);

        $name = 'request';
        $agent->tagRequest($name, 'test', time());
        usleep(5);
        $agent->send();

        $this->assertTrue(true);
    }

    public function testSpanNotStarted() {
        $this->expectException(NotStartedException::class);
        $agent = new Agent([ 'appName' => 'phpunit_x', 'key' => '1234' ]);

        $agent->stopSpan();
    }

}
