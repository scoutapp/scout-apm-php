<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Helper;

use PHPUnit\Framework\TestCase;
use Scoutapm\Helper\Timer;

use function usleep;

/** @covers \Scoutapm\Helper\Timer */
final class TimerTest extends TestCase
{
    /**
     * Matches date format like: "2019-05-23T17:03:41.260463Z"
     *
     * @link https://regex101.com/r/L85Mb2/1
     */
    private const DATE_FORMAT_VALIDATION_REGEX = '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z/';

    public function testCreatingTheTimerSetsStartTime(): void
    {
        $timer = new Timer();
        self::assertNotNull($timer->getStart());
    }

    public function testStoppingSetsStopTime(): void
    {
        $timer = new Timer();
        $timer->stop();
        self::assertNotNull($timer->getStop());
    }

    public function testStopTimeIsNullIfNotStopped(): void
    {
        $timer = new Timer();
        self::assertNull($timer->getStop());
    }

    public function testTimesAreFormatted(): void
    {
        $timer = new Timer();
        $timer->stop();

        self::assertRegExp(self::DATE_FORMAT_VALIDATION_REGEX, (string) $timer->getStart());
        self::assertRegExp(self::DATE_FORMAT_VALIDATION_REGEX, (string) $timer->getStop());
    }

    public function testDurationIsNullIfNotStopped(): void
    {
        $timer = new Timer();
        self::assertNull($timer->duration());
    }

    public function testDurationIsPositiveIfStopped(): void
    {
        $timer = new Timer();
        usleep(1);
        $timer->stop();
        self::assertTrue($timer->duration() > 0);
    }
}
