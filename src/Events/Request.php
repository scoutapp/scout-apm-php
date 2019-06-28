<?php

namespace Scoutapm\Events;

use Scoutapm\Exception\Timer\NotStartedException;
use Scoutapm\Helper\Timer;

class Request extends Event implements \JsonSerializable
{
    private $name;

    private $timer;

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

    public function startSpan(string $operation, $overrideTimestamp = null)
    {
        $span = new Span($this->agent, $operation, $this->id, $overrideTimestamp);

        // Automatically wire up the parent of this span
        if ($parent = end($this->openSpans)) {
            $span->setParentId($parent->getId());
        }

        $this->openSpans[] = $span;

        return $span;
    }

    // Stop the currently "running" span.
    // You can still tag it if needed up until the request as a whole is finished.
    public function stopSpan($overrideTimestamp = null)
    {
        $span = array_pop($this->openSpans);

        if ($span === null) {
            throw new NotStartedException();
        }

        $span->stop($overrideTimestamp);
        $this->events[] = $span;
    }

    // Add a tag to the request as a whole
    public function tag(string $tag, $value)
    {
        $tag = new TagRequest($this->agent, $tag, $value, $this->id);
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
            $array = $event->jsonSerialize();

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
