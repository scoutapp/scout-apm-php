<?php

declare(strict_types=1);

namespace Scoutapm\Helper;

use DateTime;
use DateTimeZone;
use function microtime;
use function sprintf;

class Timer
{
    private const MICROTIME_FLOAT_FORMAT           = 'U.u';
    private const FORMAT_FLOAT_TO_6_DECIMAL_PLACES = '%.6F';

    // @todo this doesn't feel like the right place for this, unless a Timer itself is serializable
    public const FORMAT_FOR_CORE_AGENT = 'Y-m-d\TH:i:s.u\Z';

    /** @var float|null */
    private $start;

    /** @var float|null */
    private $stop;

    /**
     * Creates and Starts the Timer
     */
    public function __construct(?float $override = null)
    {
        $this->start($override);
    }

    public function start(?float $override = null) : void
    {
        $this->start = $override ?? microtime(true);
    }

    public function stop(?float $override = null) : void
    {
        $this->stop = $override ?? microtime(true);
    }

    /**
     * Formats the stop time as a timestamp suitable for sending to CoreAgent
     */
    public function getStop() : ?string
    {
        if ($this->stop === null) {
            return null;
        }

        $timestamp = DateTime::createFromFormat(
            self::MICROTIME_FLOAT_FORMAT,
            sprintf(self::FORMAT_FLOAT_TO_6_DECIMAL_PLACES, $this->stop),
            new DateTimeZone('UTC')
        );

        return $timestamp->format(self::FORMAT_FOR_CORE_AGENT);
    }

    /**
     * Formats the stop time as a timestamp suitable for sending to CoreAgent
     */
    public function getStart() : ?string
    {
        $timestamp = DateTime::createFromFormat(
            self::MICROTIME_FLOAT_FORMAT,
            sprintf(self::FORMAT_FLOAT_TO_6_DECIMAL_PLACES, $this->start),
            new DateTimeZone('UTC')
        );

        return $timestamp->format(self::FORMAT_FOR_CORE_AGENT);
    }

    /**
     * Returns the duration in microseconds. If the timer has not yet been stopped yet, `null` is returned.
     */
    public function duration() : ?float
    {
        if ($this->stop === null) {
            return null;
        }

        return $this->stop - $this->start;
    }
}
