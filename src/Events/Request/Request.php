<?php

declare(strict_types=1);

namespace Scoutapm\Events\Request;

use Exception;
use Scoutapm\Connector\Command;
use Scoutapm\Connector\CommandWithChildren;
use Scoutapm\Events\Span\Span;
use Scoutapm\Events\Tag\TagRequest;
use Scoutapm\Helper\Backtrace;
use Scoutapm\Helper\Timer;
use function array_slice;

/** @internal */
class Request implements CommandWithChildren
{
    private const STACK_TRACE_THRESHOLD_SECONDS = 0.5;

    /** @var Timer */
    private $timer;

    /** @var Command[]|array<int, Command> */
    private $children = [];

    /** @var CommandWithChildren */
    private $currentCommand;

    /** @var RequestId */
    private $id;

    /** @throws Exception */
    public function __construct()
    {
        $this->id = RequestId::new();

        $this->timer = new Timer();

        $this->currentCommand = $this;
    }

    public function stop(?float $overrideTimestamp = null) : void
    {
        $this->timer->stop($overrideTimestamp);
    }

    /** @throws Exception */
    public function startSpan(string $operation, ?float $overrideTimestamp = null) : Span
    {
        $span = new Span($this->currentCommand, $operation, $this->id, $overrideTimestamp);

        $this->currentCommand->appendChild($span);

        $this->currentCommand = $span;

        return $span; // @todo do we need to return it...? exposes "internals..."
    }

    public function appendChild(Command $span) : void
    {
        $this->children[] = $span;
    }

    /**
     * Stop the currently "running" span.
     * You can still tag it if needed up until the request as a whole is finished.
     */
    public function stopSpan(?float $overrideTimestamp = null) : void
    {
        $command = $this->currentCommand;
        if (!$command instanceof Span) {
            $this->stop($overrideTimestamp);
            return;
        }

        $command->stop($overrideTimestamp);

        if ($command->duration() > self::STACK_TRACE_THRESHOLD_SECONDS) {
            $stack = Backtrace::capture();
            $stack = array_slice($stack, 4);
            $command->tag('stack', $stack);
        }

        $this->currentCommand = $command->parent();
    }

    /**
     * Add a tag to the request as a whole
     *
     * @param mixed $value
     */
    public function tag(string $tagName, $value) : void
    {
        $this->appendChild(new TagRequest($tagName, $value, $this->id));
    }

    /**
     * turn this object into a list of commands to send to the CoreAgent
     *
     * @return array<string, array<string, array<int, array<string, (string|array|bool|null)>>>>
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

        foreach ($this->children as $child) {
            foreach ($child->jsonSerialize() as $value) {
                $commands[] = $value;
            }
        }

        $commands[] = [
            'FinishRequest' => [
                'request_id' => $this->id->toString(),
                'timestamp' => $this->timer->getStop(),
            ],
        ];

        return [
            'BatchCommand' => ['commands' => $commands],
        ];
    }

    /**
     * You probably don't need this, it's used in testing.
     * Returns all events that have occurred in this Request.
     *
     * @todo remove
     * @deprecated
     * @internal
     * @return Command[]|array<int, Command>
     */
    public function getEvents() : array
    {
        return $this->children;
    }
}
