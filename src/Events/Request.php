<?php

namespace Scoutapm\Events;

use Scoutapm\Exception\Timer\NotStartedException;
use Scoutapm\Helper\Timer;

class Request extends Event implements \JsonSerializable
{
    private $name;

    private $timer;

    /** @var Event[] full event stack */
    private $stack = [];

    private $openSpans = [];

    public function __construct(\Scoutapm\Agent $agent, string $name)
    {
        parent::__construct();

        $this->name = $name;
        $this->timer = new Timer();
    }

    public function start($override = null) : void
    {
        $this->timer->start($override);
    }

    /**
     * @throws NotStartedException
     */
    public function stop()
    {
        $this->timer->stop();
    }

    public function addSpan(Span $span)
    {
        if ($parent = end($this->openSpans)) {
            $span->setParentId($parent->getId());
        }

        $span->setRequestId($this->id);
        $this->openSpans[] = $span;

        $this->stack[] = $span;
    }

    public function stopSpan()
    {
        $span = array_pop($this->openSpans);

        if ($span === null) {
            throw new NotStartedException();
        }

        $span->stop();
        $this->stack[] = $span;
    }

    public function tagRequest(TagRequest $tag)
    {
        $tag->setRequestId($this->id);
    }

    public function tagSpan(TagSpan $tag, $current)
    {
        $tag->setRequestId($this->id);

        $this->stack[] = $tag;
    }

    public function getStartArray()
    {
        $startTime = $this->timer->getStart();
        if (count($this->stack) > 0) {
            $event = reset($this->stack);
            $startTime = $event->getStartTime();
        }

        return [
            'StartRequest' => [
                'request_id' => $this->getId(),
                'timestamp' => $startTime,
            ]
        ];
    }

    public function getStopArray()
    {
        $stopTime = $this->timer->getStop();
        if (count($this->stack) > 0) {
            $event = end($this->stack);
            $stopTime = $event->getStopTime();
        }

        return [
            'FinishRequest' => [
                'request_id' => $this->getId(),
                'timestamp' => $stopTime,
            ]
        ];
    }

    public function jsonSerialize() : array
    {
        $output = [$this->getStartArray()];

        $parents = [];
        foreach ($this->stack as $event) {
            $array = $event->getEventArray($parents);

            foreach ($array as $value) {
                $output[] = $value;
            }
        }

        $output[] = $this->getStopArray();

        return $output;
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
