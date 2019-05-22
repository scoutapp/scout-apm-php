<?php

namespace Scoutapm\Events;

use Scoutapm\Helper\Timer;

class Span extends Event
{
    private $requestId;

    private $parentId;

    private $name;

    private $timer;

    public function __construct(\Scoutapm\Agent $agent, string $name)
    {
        parent::__construct($agent);

        $this->name = $name;
        $this->timer = new Timer();
    }

    public function start($override = null)
    {
        $this->timer->start($override);
    }

    public function stop()
    {
        $this->timer->stop();
    }

    public function setRequestId(string $requestId)
    {
        $this->requestId = $requestId;
    }

    public function setParentId(string $parentId)
    {
        $this->parentId = $parentId;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function getStartTime()
    {
        return $this->timer->getStart();
    }

    public function getStopTime()
    {
        return $this->timer->getStop();
    }

    public function getStartArray()
    {
        return ['StartSpan' => [
            'request_id' => $this->requestId,
            'span_id' => $this->id,
            'parent_id' => $this->parentId,
            'operation' => $this->name,
            'timestamp' => $this->getStartTime(),
        ]];
    }

    public function getStopArray()
    {
        return ['StopSpan' => [
            'request_id' => $this->requestId,
            'span_id' => $this->id,
            'timestamp' => $this->getStopTime(),
        ]];
    }

    public function getEventArray(array &$parents): array
    {
        $currentParent = array_pop($parents);

        if ($currentParent == $this) {
            return [$this->getStopArray()];
        }

        array_push($parents, $this);
        return [$this->getStartArray()];
    }
}
