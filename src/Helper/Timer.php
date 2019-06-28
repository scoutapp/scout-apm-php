<?php

namespace Scoutapm\Helper;

class Timer
{
    private $start = null;

    private $stop = null;

    /**
     * Creates and Starts the Timer
     */
    public function __construct($override = null)
    {
        $this->start($override);
    }

    public function start($override = null)
    {
        $this->start = $override ?? microtime(true);
    }

    public function stop($override = null)
    {
        $this->stop = $override ?? microtime(true);
    }

    /**
     * Formats the stop time as a timestamp suitable for sending to CoreAgent
     **/
    public function getStop()
    {
        if ($this->stop == null) {
            return null;
        }

        $timestamp = \DateTime::createFromFormat('U.u', sprintf('%.6F', $this->stop));
        $timestamp->setTimeZone(new \DateTimeZone('UTC'));
        return $timestamp->format('Y-m-d\TH:i:s.u\Z');
    }

    /**
     * Formats the stop time as a timestamp suitable for sending to CoreAgent
     **/
    public function getStart()
    {
        $timestamp = \DateTime::createFromFormat('U.u', sprintf('%.6F', $this->start));
        $timestamp->setTimeZone(new \DateTimeZone('UTC'));
        return $timestamp->format('Y-m-d\TH:i:s.u\Z');
    }
}
