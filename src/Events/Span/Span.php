<?php

declare(strict_types=1);

namespace Scoutapm\Events\Span;

use Exception;
use Scoutapm\Connector\SerializableMessage;
use Scoutapm\Events\Request\RequestId;
use Scoutapm\Events\Tag\TagSpan;
use Scoutapm\Helper\Timer;

/** @internal */
class Span implements SerializableMessage
{
    /** @var SpanId */
    private $id;

    /** @var RequestId */
    private $requestId;

    /** @var SpanId|null */
    private $parentId;

    /** @var string */
    private $name;

    /** @var Timer */
    private $timer;

    /** @var TagSpan[]|array<int, TagSpan> */
    private $tags;

    /** @throws Exception */
    public function __construct(string $name, RequestId $requestId, ?float $override = null)
    {
        $this->id = SpanId::new();

        $this->name      = $name;
        $this->requestId = $requestId;

        $this->tags = [];

        $this->timer = new Timer($override);
    }

    public function id() : SpanId
    {
        return $this->id;
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

    /** @param mixed $value */
    public function tag(string $tag, $value) : void
    {
        $this->tags[] = new TagSpan($tag, $value, $this->requestId, $this->id);
    }

    public function setParentId(SpanId $parentId) : void
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

    /** @return TagSpan[]|array<int, TagSpan> */
    public function getTags() : array
    {
        return $this->tags;
    }

    /** @return array<int, array<string, (string|array|bool|null)>> */
    public function jsonSerialize() : array
    {
        $commands   = [];
        $commands[] = [
            'StartSpan' => [
                'request_id' => $this->requestId->toString(),
                'span_id' => $this->id->toString(),
                'parent_id' => $this->parentId ? $this->parentId->toString() : null,
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
                'request_id' => $this->requestId->toString(),
                'span_id' => $this->id->toString(),
                'timestamp' => $this->getStopTime(),
            ],
        ];

        return $commands;
    }
}
