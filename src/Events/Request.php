<?php

namespace Scoutapm\Events;

use Scoutapm\Exception\Timer\NotStartedException;
use Scoutapm\Helper\Timer;

class Request extends Event implements \JsonSerializable
{
    private $name;

    private $timer;

    private $events = [];

    private $openSpans = [];

    private $stack = [];

    public function __construct(string $name)
    {
        parent::__construct();

        $this->name = $name;
        $this->timer = new Timer();
    }

    public function start($override = null) : void
    {
        $this->timer->start($override);
    }

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
        $this->events[] = $span;
        $this->stack[] = $span;
    }

    public function tagRequest(TagRequest $tag)
    {
        $tag->setRequestId($this->id);
        $this->events[] = $tag;
    }

    public function tagSpan(TagSpan $tag, $current)
    {
        // todo: this needs to be refactored. You can't tag the previous span twice.
        if ($current) {
            $span = end($this->openSpans);
        } else {
            $span = end($this->events);
        }

        $tag->setSpanId($span->getId());
        $tag->setRequestId($this->id);

        $this->events[] = $tag;
        $this->stack[] = $tag;
    }

    public function jsonSerialize() : array
    {
        $events = $this->stack;

        $startTime = $this->timer->getStart();
        if (count($events) > 0) {
            $event = reset($events);
            $startTime = $event->getStartTime();
        }

        $output = [
            [
                'StartRequest' => [
                    'request_id' => $this->getId(),
                    'timestamp' => $startTime,
                ]
            ],
        ];

        $parents = [];
        foreach ($events as $event) {
            $currentParent = end($parents);

            if ($event instanceof Span) {
                if ($currentParent == $event) {
                    array_pop($parents);

                    $arr = [$event->getStopArray()];
                    foreach ($arr as $value) {
                        $output[] = $value;
                    }
                    continue;
                }

                $parents[] = $event;

                $arr = [$event->getStartArray()];
                foreach ($arr as $value) {
                    $output[] = $value;
                }

                continue;
            }

            if ($event instanceof Tag) {

                $event->setSpanId($currentParent->getId());

                $arr = $event->getArrays();
                foreach ($arr as $value) {
                    $output[] = $value;
                }

                continue;
            }
        }

        $lastestTime = microtime(true);
        if (isset($event)) {
            $lastestTime = $event->getStopTime();
        }

        $output[] = [
            'FinishRequest' => [
                'request_id' => $this->getId(),
                'timestamp' => $lastestTime,
            ]
        ];

        return $output;
    }
}
