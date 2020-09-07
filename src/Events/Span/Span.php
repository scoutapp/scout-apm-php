<?php

declare(strict_types=1);

namespace Scoutapm\Events\Span;

use Exception;
use Scoutapm\Connector\Command;
use Scoutapm\Connector\CommandWithChildren;
use Scoutapm\Connector\CommandWithParent;
use Scoutapm\Events\Request\RequestId;
use Scoutapm\Events\Tag\Tag;
use Scoutapm\Events\Tag\TagSpan;
use Scoutapm\Helper\Backtrace;
use Scoutapm\Helper\RecursivelyCountSpans;
use Scoutapm\Helper\Timer;
use function array_filter;
use function array_map;
use function strpos;

/** @internal */
class Span implements CommandWithParent, CommandWithChildren
{
    private const STACK_TRACE_THRESHOLD_SECONDS = 0.5;

    public const INSTRUMENT_CONTROLLER = 'Controller';
    public const INSTRUMENT_JOB        = 'Job';
    public const INSTRUMENT_MIDDLEWARE = 'Middleware';

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

    public function cleanUp() : void
    {
        array_map(
            static function (Command $command) : void {
                $command->cleanUp();
            },
            $this->children
        );
        unset($this->id, $this->requestId, $this->parent, $this->children, $this->name, $this->timer);
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

        // phpcs:disable SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed
        if ($this->duration() >= self::STACK_TRACE_THRESHOLD_SECONDS && ! $this->isControllerJobOrMiddleware()) {
            $this->tag(Tag::TAG_STACK_TRACE, Backtrace::capture());
        }
        // phpcs:enable
    }

    private function isControllerJobOrMiddleware() : bool
    {
        return strpos($this->name, self::INSTRUMENT_CONTROLLER) === 0
            || strpos($this->name, self::INSTRUMENT_MIDDLEWARE) === 0
            || strpos($this->name, self::INSTRUMENT_JOB) === 0;
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

    public function collectedSpans() : int
    {
        return RecursivelyCountSpans::forCommands($this->children);
    }

    /**
     * @internal
     * @deprecated
     *
     * @return TagSpan[]|array<int, TagSpan>
     *
     * @todo remove - only used in tests
     */
    public function getTags() : array
    {
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
