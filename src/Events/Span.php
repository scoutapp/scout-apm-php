<?php

declare(strict_types=1);

namespace Scoutapm\Events;

use JsonSerializable;
use Scoutapm\Agent;
use Scoutapm\Helper\Timer;

class Span extends Event implements JsonSerializable
{
    /** @var string */
    private $requestId;

    /** @var string|null */
    private $parentId;

    /** @var string */
    private $name;

    /** @var Timer */
    private $timer;

    /** @var array<int, TagSpan> */
    private $tags;

    public function __construct(Agent $agent, string $name, string $requestId, ?float $override = null)
    {
        parent::__construct($agent);

        $this->name      = $name;
        $this->requestId = $requestId;

        $this->tags = [];

        $this->timer = new Timer($override);
    }

    /**
     * Do not call this directly - use Request#stopSpan() or Agent#stopSpan() to correctly handle bookkeeping
     *
     * @internal
     */
    public function stop(?float $override = null) : void
    {
        $this->timer->stop($override);
    }

    /**
     * Used if you need to start a span, but don't get a good name for it until later in its execution (or even after
     * it's complete).
     */
    public function updateName(string $name) : void
    {
        $this->name = $name;
    }

    public function tag(string $tag, string $value) : void
    {
        $tagSpan      = new TagSpan($this->agent, $tag, $value, $this->requestId, $this->id);
        $this->tags[] = $tagSpan;
    }

    public function setParentId(string $parentId) : void
    {
        $this->parentId = $parentId;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function getStartTime() : ?string
    {
        return $this->timer->getStart();
    }

    public function getStopTime() : ?string
    {
        return $this->timer->getStop();
    }

    public function duration() : ?float
    {
        return $this->timer->duration();
    }

    /** @return array<int, TagSpan> */
    public function getTags() : array
    {
        return $this->tags;
    }

    /** @return mixed[] */
    public function jsonSerialize() : array
    {
        $commands   = [];
        $commands[] = [
            'StartSpan' => [
                'request_id' => $this->requestId,
                'span_id' => $this->id,
                'parent_id' => $this->parentId,
                'operation' => $this->name,
                'timestamp' => $this->getStartTime(),
            ],
        ];

        foreach ($this->tags as $tag) {
            foreach ($tag->jsonSerialize() as $value) {
                $commands[] = $value;
            }
        }

        $commands[] = [
            'StopSpan' => [
                'request_id' => $this->requestId,
                'span_id' => $this->id,
                'timestamp' => $this->getStopTime(),
            ],
        ];

        return $commands;
    }
}
