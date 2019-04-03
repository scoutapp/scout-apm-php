<?php

namespace Scoutapm\Events;

use Ramsey\Uuid\Uuid;

abstract class Event
{
    protected $id;

    protected $timestamp;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->toString();
        $timestamp = \DateTime::createFromFormat('U.u', sprintf('%.6F', microtime(true)));
        $timestamp->setTimeZone(new \DateTimeZone('UTC'));
        $this->timestamp = $timestamp->format('Y-m-d\TH:i:s.u\Z');
    }

    public function getId() : string
    {
        return $this->id;
    }

    public function getTimestamp() : string
    {
        return $this->timestamp;
    }

    abstract public function getEventArray(array &$parents) : array;
}
