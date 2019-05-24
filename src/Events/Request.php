<?php

namespace Scoutapm\Events;

use Scoutapm\Exception\Timer\NotStartedException;
use Scoutapm\Helper\Timer;

class Request extends Event implements \JsonSerializable
{
    private $name;

    private $timer;

    /** @var Every event that happens (Span, Tags, etc) is added here. */
    private $events = [];

    /** @var The currently open / running Spans */
    private $openSpans = [];

    public function __construct(\Scoutapm\Agent $agent, string $name)
    {
        parent::__construct($agent);

        $this->name = $name;
        $this->timer = new Timer();
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

        if ($span === null) {
            throw new NotStartedException();
        }

        $span->stop();
        $this->events[] = $span;
    }

    public function tagRequest(TagRequest $tag)
    {
        $tag->setRequestId($this->id);
        $this->events[] = $tag;
    }

    public function tagSpan(TagSpan $tag)
    {
        $tag->setRequestId($this->id);
        $this->events[] = $tag;
    }

    /**
     * turn this object into a list of commands to send to the CoreAgent
     *
     * @return array[core agent commands]
     */
    public function jsonSerialize() : array
    {
        $commands = [];
        $commands[] = ['StartRequest' => [
            'request_id' => $this->getId(),
            'timestamp' => $this->timer->getStart(),
        ]];

        foreach ($this->events as $event) {
            $array = $event->serialize();

            foreach ($array as $value) {
                $commands[] = $value;
            }
        }

        $commands[] = ['FinishRequest' => [
            'request_id' => $this->getId(),
            'timestamp' => $this->timer->getStop(),
        ]];

        return $commands;
    }

    /**
     * You probably don't need this, it's used in testing.
     * Returns all events that have occurred in this Request.
     *
     * @return array[Events]
     */
    public function getEvents() : array
    {
        return $this->events;
    }
    
}
