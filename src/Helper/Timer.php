<?php

namespace Scoutapm\Helper;

use Scoutapm\Exception\Timer\NotStartedException;
use Scoutapm\Exception\Timer\NotStoppedException;

class Timer
{
    private $start = null;

    private $stop = null;

    public function start($override = null)
    {
        $this->start = $override ?? microtime(true);
    }

    public function stop()
    {
        if ($this->start === null) {
            throw new NotStartedException();
        }

        $this->stop = microtime(true);
    }

    public function getDuration() : float
    {
        if ($this->stop === null) {
            throw new NotStoppedException();
        }

        return $this->toMicro($this->stop - $this->start);
    }

    public function getElapsed() : float
    {
        if ($this->start === null) {
            throw new NotStartedException();
        }

        return ($this->stop === null) ?
            $this->toMicro(microtime(true) - $this->start) :
            $this->getDuration();
    }

    public function getStop()
    {
        $timestamp = \DateTime::createFromFormat('U.u', sprintf('%.6F', $this->stop));
        $timestamp->setTimeZone(new \DateTimeZone('UTC'));
        return $timestamp->format('Y-m-d\TH:i:s.u\Z');
    }

    public function getStart()
    {
        $timestamp = \DateTime::createFromFormat('U.u', sprintf('%.6F', $this->start));
        $timestamp->setTimeZone(new \DateTimeZone('UTC'));
        return $timestamp->format('Y-m-d\TH:i:s.u\Z');
    }

    private function toMicro(float $num) : float
    {
        return $num * 1000000;
    }
}
