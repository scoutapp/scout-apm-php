<?php
namespace Scoutapm\Tests\Helper;

use \Scoutapm\Helper\Timer;
use \PHPUnit\Framework\TestCase;

/**
 * Test Case for @see \Scoutapm\Helper\Timer
 */
final class TimerTest extends TestCase {

  /**
   * @covers \Scoutapm\Helper\Timer::start
   * @covers \Scoutapm\Helper\Timer::stop
   * @covers \Scoutapm\Helper\Timer::getDuration
   * @covers \Scoutapm\Helper\Timer::toMicro
   */
  public function testCanBeStartedAndStoppedWithDuration() {
    $timer = new Timer();
    $duration = rand(25, 100);

    $timer->start();
    usleep($duration);
    $timer->stop();

    $this->assertGreaterThanOrEqual($duration, $timer->getDuration());
  }

  /**
   * @depends testCanBeStartedAndStoppedWithDuration
   *
   * @covers \Scoutapm\Helper\Timer::start
   * @covers \Scoutapm\Helper\Timer::getDuration
   */
  public function testCanBeStartedWithForcingDurationException() {
    $this->expectException(\Scoutapm\Exception\Timer\NotStoppedException::class);
    $timer = new Timer();
    $timer->start();
    $timer->getDuration();
  }

  /**
   * @depends testCanBeStartedWithForcingDurationException
   *
   * @covers \Scoutapm\Helper\Timer::stop
   */
  public function testCannotBeStoppedWithoutStart() {
    $this->expectException(\Scoutapm\Exception\Timer\NotStartedException::class);
    $timer = new Timer();
    $timer->stop();
  }

}
