<?php
namespace Scoutapm\Tests;

use \Scoutapm\Agent;
use \Scoutapm\Request\Summary;
use \PHPUnit\Framework\TestCase;

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
    $agent->startRequest($name);
    usleep(5);
    $agent->stopRequest($name);

    $summary = $agent->getRequest($name)->getSummary();

    $this->assertArrayHasKey('duration', $summary);

    $this->assertGreaterThanOrEqual(10, $summary['duration']);
  }

  /**
   * @depends testStartAndStopARequest
   *
   * @covers \Scoutapm\Agent::__construct
   * @covers \Scoutapm\Agent::getRequest
   */
  public function testForceErrorOnUnknownRequest() {
    $this->expectException(\Scoutapm\Exception\Request\UnknownRequestException::class);
    $agent = new Agent([ 'appName' => 'phpunit_x', 'key' => '1234' ]);

    $agent->getRequest('unknown');
  }

  /**
   * @depends testForceErrorOnUnknownRequest
   *
   * @covers \Scoutapm\Agent::__construct
   * @covers \Scoutapm\Agent::stopRequest
   */
  public function testForceErrorOnUnstartedRequest() {
    $this->expectException(\Scoutapm\Exception\Request\UnknownRequestException::class);

    $agent = new Agent([ 'appName' => 'phpunit_2', 'key' => '1234' ]);

    $agent->stopRequest('unknown');
  }

}
