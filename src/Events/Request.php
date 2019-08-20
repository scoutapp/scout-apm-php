<?php

declare(strict_types=1);

namespace Scoutapm\Events;

use JsonSerializable;
use Scoutapm\Agent;
use Scoutapm\Exception\Timer\NotStarted;
use Scoutapm\Helper\Backtrace;
use Scoutapm\Helper\Timer;
use function array_pop;
use function array_slice;
use function end;

class Request extends Event implements JsonSerializable
{
    /** @var Timer */
    private $timer;

    /** @var array<int, TagRequest|Span> */
    private $events = [];

    /** @var array<int, Span> */
    private $openSpans = [];

    public function __construct(Agent $agent)
    {
        parent::__construct($agent);

        $this->timer = new Timer();
    }

    public function stop() : void
    {
        $this->timer->stop();
    }

    public function startSpan(string $operation, ?float $overrideTimestamp = null) : Span
    {
        $span = new Span($this->agent, $operation, $this->id, $overrideTimestamp);

        $parent = end($this->openSpans);
        // Automatically wire up the parent of this span
        if ($parent) {
            $span->setParentId($parent->getId());
        }

        $this->openSpans[] = $span;

        return $span;
    }

    /**
     * Stop the currently "running" span.
     * You can still tag it if needed up until the request as a whole is finished.
     *
     * @throws NotStarted
     */
    public function stopSpan(?float $overrideTimestamp = null) : void
    {
        /** @var Span|null $span */
        $span = array_pop($this->openSpans);

        if ($span === null) {
            throw new NotStarted();
        }

        $span->stop($overrideTimestamp);

        $threshold = 0.5;
        if ($span->duration() > $threshold) {
            $stack = Backtrace::capture();
            $stack = array_slice($stack, 4);
            $span->tag('stack', $stack);
        }

        $this->events[] = $span;
    }

    /**
     * Add a tag to the request as a whole
     */
    public function tag(string $tag, string $value) : void
    {
        $tag            = new TagRequest($this->agent, $tag, $value, $this->id);
        $this->events[] = $tag;
    }

    /**
     * turn this object into a list of commands to send to the CoreAgent
     *
     * @return array<int, array<string, (string|array|null|bool)>>
     */
    public function jsonSerialize() : array
    {
        $commands   = [];
        $commands[] = [
            'StartRequest' => [
                'request_id' => $this->getId(),
                'timestamp' => $this->timer->getStart(),
            ],
        ];

        foreach ($this->events as $event) {
            $array = $event->jsonSerialize();

            foreach ($array as $value) {
                $commands[] = $value;
            }
        }

        $commands[] = [
            'FinishRequest' => [
                'request_id' => $this->getId(),
                'timestamp' => $this->timer->getStop(),
            ],
        ];

        return $commands;
    }

    /**
     * You probably don't need this, it's used in testing.
     * Returns all events that have occurred in this Request.
     *
     * @return array<int, Event>
     */
    public function getEvents() : array
    {
        return $this->events;
    }
}
