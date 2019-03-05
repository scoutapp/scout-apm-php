<?php
namespace Scoutapm\Tests\Helper;

use \Scoutapm\Agent;
use \Scoutapm\Helper\Config;
use \PHPUnit\Framework\TestCase;

/**
 * Test Case for @see \Scoutapm\Helper\Config
 */
final class ConfigTest extends TestCase {

  /**
   * @covers \Scoutapm\Helper\Config::__construct
   * @covers \Scoutapm\Agent::getConfig
   * @covers \Scoutapm\Helper\Config::getDefaultConfig
   * @covers \Scoutapm\Helper\Config::asArray
   */
  public function testControlDefaultConfig() {
    $appName = sprintf('app_name_%d', rand(10,99));
    $key = sprintf('key_%d', rand(10, 99));
    $agent = new Agent([ 'appName' => $appName, 'key' => $key ]);

    $config = $agent->getConfig()->asArray();

    $this->assertArrayHasKey('appName', $config);
    $this->assertArrayHasKey('key', $config);
    $this->assertArrayHasKey('apiVersion', $config);
    $this->assertArrayHasKey('socketLocation', $config);
    
    $this->assertEquals($config['appName'], $appName);
    $this->assertEquals($config['key'], $key);
    $this->assertEquals($config['apiVersion'], '1.0');
    $this->assertEquals($config['socketLocation'], '/tmp/core-agent.sock');
  }

  /**
   * @depends testControlDefaultConfig
   *
   * @covers \Scoutapm\Helper\Config::__construct
   * @covers \Scoutapm\Agent::getConfig
   * @covers \Scoutapm\Helper\Config::getDefaultConfig
   * @covers \Scoutapm\Helper\Config::asArray
   */
  public function testControlInjectedConfig() {
      $settings = [
      'appName'         => sprintf('app_name_%d', rand(10, 99)),
      'key'             => sprintf('key_%d', rand(10, 99)),
      'apiVersion'      =>  '2.0',
      'socketLocation'  => '/abcd.sock',
    ];

    $agent = new Agent($settings);

    $config = $agent->getConfig()->asArray();
    foreach($settings as $key => $value) {
        $this->assertEquals($config[$key], $settings[$key], 'key: ' . $key);
    }
  }

  /**
   * @depends testControlInjectedConfig
   *
   * @covers \Scoutapm\Helper\Config::__construct
   * @covers \Scoutapm\Agent::getConfig
   * @covers \Scoutapm\Helper\Config::getDefaultConfig
   * @covers \Scoutapm\Helper\Config::get
   */
  public function testGetConfig() {
    $settings = [
      'appName' => sprintf('app_name_%d', rand(10, 99)),
      'key' => '1234',
    ];

    $agent = new Agent($settings);
    $this->assertEquals($agent->getConfig()->get('appName'), $settings['appName']);
  }

}
