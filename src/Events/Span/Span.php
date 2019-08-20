<?php

declare(strict_types=1);

namespace Scoutapm\Events\Span;

use Exception;
use JsonSerializable;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Scoutapm\Events\Tag\SpanTag;
use Scoutapm\Helper\Timer;

/** @internal */
class Span implements JsonSerializable
{
    /** @var UuidInterface */
    private $requestId;

    /** @var UuidInterface|null */
    private $parentId;

    /** @var string */
    private $name;

    /** @var Timer */
    private $timer;

    /** @var SpanTag[]|array<int, SpanTag> */
    private $tags;

    /** @var UuidInterface */
    private $id;

    /** @throws Exception */
    public function __construct(string $name, UuidInterface $requestId, ?float $override = null)
    {
        $this->id = Uuid::uuid4();

        $this->name      = $name;
        $this->requestId = $requestId;

        $this->tags = [];

        $this->timer = new Timer($override);
    }

    public function id() : UuidInterface
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
        $this->tags[] = new SpanTag($tag, $value, $this->requestId, $this->id);
    }

    public function setParentId(UuidInterface $parentId) : void
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

    /** @return SpanTag[]|array<int, SpanTag> */
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
                'request_id' => $this->requestId,
                'span_id' => $this->id->toString(),
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
                'span_id' => $this->id->toString(),
                'timestamp' => $this->getStopTime(),
            ],
        ];

        return $commands;
    }
}
