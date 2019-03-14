<?php

namespace Scoutapm\Events;

use Scoutapm\Helper\Timer;

class Request extends Event implements \JsonSerializable
{
    private $name;

    private $timer;

    private $events = [];

    private $openSpans = [];

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
    }

    public function stopSpan()
    {
        $span = array_pop($this->openSpans);
        $span->stop();
        $this->events[] = $span;
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
    }

    public function jsonSerialize() : array
    {
        $output = [
            [
                'StartRequest' => [
                    'request_id' => $this->getId(),
                    'timestamp' => $this->timer->getStart(),
                ]
            ],
        ];

        $events = $this->events;
        foreach ($events as $event) {
            $arr = $event->getArrays();
            foreach ($arr as $value) {
                $output[] = $value;
            }
        }

        $output[] = [
            'FinishRequest' => [
                'request_id' => $this->getId(),
                'timestamp' => $this->timer->getStop(),
            ]
        ];


        return $output;
    }
}
