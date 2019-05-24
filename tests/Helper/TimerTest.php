<?php
namespace Scoutapm\Tests\Helper;

use \PHPUnit\Framework\TestCase;
use \Scoutapm\Helper\Timer;

/**
 * Test Case for @see \Scoutapm\Helper\Timer
 */
final class TimerTest extends TestCase
{
    public function testCreatingTheTimerSetsStartTime()
    {
        $timer = new Timer();
        $this->assertNotNull($timer->getStart());
    }

    public function testStoppingSetsStopTime()
    {
        $timer = new Timer();
        $timer->stop();
        $this->assertNotNull($timer->getStop());
    }

    public function testStopTimeIsNullIfNotStopped()
    {
        $timer = new Timer();
        $this->assertNull($timer->getStop());
    }

    public function testTimesAreFormatted()
    {
        $timer = new Timer();
        $timer->stop();

        // Matches date format like: "2019-05-23T17:03:41.260463Z"
        // https://regex101.com/r/L85Mb2/1
        $dateRegex = '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z/';

        $this->assertEquals(1, preg_match($dateRegex, $timer->getStart()));
        $this->assertEquals(1, preg_match($dateRegex, $timer->getStop()));
    }
}
