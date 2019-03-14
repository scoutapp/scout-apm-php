<?php

namespace Scoutapm\Events;

use Ramsey\Uuid\Uuid;
use Scoutapm\Helper\Timer;

class Span extends Event
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
        parent::__construct();

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

    public function getArrays()
    {
        return [
            ['StartSpan' => [
                'request_id' => $this->getRequestId(),
                'span_id' => $this->getId(),
                'parent_id' => $this->getParentId(),
                'operation' => $this->getName(),
                'timestamp' => $this->timer->getStart(),
            ]],
            ['StopSpan' => [
                'request_id' => $this->getRequestId(),
                'span_id' => $this->getId(),
                'timestamp' => $this->timer->getStop(),
            ]],
        ];
    }
}
