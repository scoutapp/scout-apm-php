<?php

declare(strict_types=1);

namespace Scoutapm\Events\Request;

use Exception;
use JsonSerializable;
use Scoutapm\Events\Exception\SpanHasNotBeenStarted;
use Scoutapm\Events\Span\Span;
use Scoutapm\Events\Tag\RequestTag;
use Scoutapm\Helper\Backtrace;
use Scoutapm\Helper\Timer;
use function array_pop;
use function array_slice;
use function end;

/** @internal */
class Request implements JsonSerializable
{
    /** @var Timer */
    private $timer;

    /** @var RequestTag[]|Span[]|array<int, (RequestTag|Span)> */
    private $events = [];

    /** @var Span[]|array<int, Span> */
    private $openSpans = [];

    /** @var RequestId */
    private $id;

    /** @throws Exception */
    public function __construct()
    {
        $this->id = RequestId::new();

        $this->timer = new Timer();
    }

    public function stop() : void
    {
        $this->timer->stop();
    }

    /** @throws Exception */
    public function startSpan(string $operation, ?float $overrideTimestamp = null) : Span
    {
        $span = new Span($operation, $this->id, $overrideTimestamp);

        $parent = end($this->openSpans);
        // Automatically wire up the parent of this span
        if ($parent instanceof Span) {
            $span->setParentId($parent->id());
        }

        $this->openSpans[] = $span;

        return $span;
    }

    /**
     * Stop the currently "running" span.
     * You can still tag it if needed up until the request as a whole is finished.
     *
     * @throws SpanHasNotBeenStarted
     */
    public function stopSpan(?float $overrideTimestamp = null) : void
    {
        /** @var Span|null $span */
        $span = array_pop($this->openSpans);

        if ($span === null) {
            throw SpanHasNotBeenStarted::fromRequest($this->id);
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
    public function tag(string $tagName, string $value) : void
    {
        $this->events[] = new RequestTag($tagName, $value, $this->id);
    }

    /**
     * turn this object into a list of commands to send to the CoreAgent
     *
     * @return array<int, array<string, (string|array|bool|null)>>
     */
    public function jsonSerialize() : array
    {
        $commands   = [];
        $commands[] = [
            'StartRequest' => [
                'request_id' => $this->id->toString(),
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
                'request_id' => $this->id->toString(),
                'timestamp' => $this->timer->getStop(),
            ],
        ];

        return $commands;
    }

    /**
     * You probably don't need this, it's used in testing.
     * Returns all events that have occurred in this Request.
     *
     * @return RequestTag[]|Span[]|array<int, (RequestTag|Span)>
     */
    public function getEvents() : array
    {
        return $this->events;
    }
}
