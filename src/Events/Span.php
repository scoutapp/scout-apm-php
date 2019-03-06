<?php

namespace Scoutapm\Events;

use Ramsey\Uuid\Uuid;
use Scoutapm\Helper\Timer;

class Span extends Event implements \JsonSerializable
{
    private $request_id;

    private $parent_id;

    private $name;

    private $timer;

    private $summary = [
        'duration'  => 0.0,
    ];

    public function __construct(string $name, string $requestId, string $parentId = null)
    {
        $this->id = Uuid::uuid4()->toString();
        $timestamp = \DateTime::createFromFormat('U.u', sprintf('%.6F', microtime(true)));
        $timestamp->setTimeZone(new \DateTimeZone('UTC'));
        $this->timestamp = $timestamp->format('Y-m-d\TH:i:s.u\Z');

        $this->setName($name);
        $this->request_id = $requestId;
        $this->parent_id = $parentId;
        $this->timer = new Timer();
    }

    public function start($override = null)
    {
        $this->timer->start($override);
    }

    public function stop(int $duration = null)
    {
        $this->timer->stop();

        $this->summary['duration']  = $duration ?? round($this->timer->getDuration(), 3);
    }

    public function getRequestId() : string
    {
        return $this->request_id;
    }

    public function getParentId() : ?string
    {
        return $this->parent_id;
    }

    public function setName(string $name)
    {
        $this->name = $name;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function getSummary() : array
    {
        return $this->summary;
    }

    public function getStartData() : array
    {
        return [
            'StartSpan' => [
                'request_id' => $this->getRequestId(),
                'span_id' => $this->getId(),
                'parent_id' => $this->getParentId(),
                'operation' => $this->getName(),
                'timestamp' => $this->timer->getStart(),
            ]
        ];
    }

    public function getStopData() : array
    {
        return [
            'StopSpan' => [
                'request_id' => $this->getRequestId(),
                'span_id' => $this->getId(),
                'timestamp' => $this->timer->getStop(),
            ]
        ];
    }

    public function getArrays() : array
    {
        return [$this->getStartData(), $this->getStopData()];
    }

    public function jsonSerialize() : array
    {
        return $this->getStartData() + $this->getStopData();
    }
}
