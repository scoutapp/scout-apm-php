<?php

declare(strict_types=1);

namespace Scoutapm\Events\Span;

use Exception;
use Scoutapm\Connector\Command;
use Scoutapm\Connector\CommandWithChildren;
use Scoutapm\Connector\CommandWithParent;
use Scoutapm\Connector\TreeCommand;
use Scoutapm\Events\Request\RequestId;
use Scoutapm\Events\Tag\TagSpan;
use Scoutapm\Helper\Timer;

/** @internal */
class Span implements CommandWithParent, CommandWithChildren
{
    /** @var SpanId */
    private $id;

    /** @var RequestId */
    private $requestId;

    /** @var CommandWithChildren */
    private $parent;

    /** @var Command[]|array<int, Command> */
    private $children = [];

    /** @var string */
    private $name;

    /** @var Timer */
    private $timer;

    /** @throws Exception */
    public function __construct(CommandWithChildren $parent, string $name, RequestId $requestId, ?float $override = null)
    {
        $this->id = SpanId::new();

        $this->parent = $parent;

        $this->name      = $name;
        $this->requestId = $requestId;

        $this->timer = new Timer($override);
    }

    public function id() : SpanId
    {
        return $this->id;
    }

    public function parent() : CommandWithChildren
    {
        return $this->parent;
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

    public function appendChild(Command $command) : void
    {
        $this->children[] = $command;
    }

    /** @param mixed $value */
    public function tag(string $tag, $value) : void
    {
        $this->appendChild(new TagSpan($tag, $value, $this->requestId, $this->id));
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
        // @todo possibly deprecate? do we need this?
        return array_filter(
            $this->children,
            static function ($item) {
                return $item instanceof TagSpan;
            }
        );
    }

    /** @return array<int, array<string, (string|array|bool|null)>> */
    public function jsonSerialize() : array
    {
        $commands   = [];
        $commands[] = [
            'StartSpan' => [
                'request_id' => $this->requestId->toString(),
                'span_id' => $this->id->toString(),
                'parent_id' => $this->parent instanceof self ? $this->parent->id->toString() : null,
                'operation' => $this->name,
                'timestamp' => $this->getStartTime(),
            ],
        ];

        foreach ($this->children as $child) {
            foreach ($child->jsonSerialize() as $value) {
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
