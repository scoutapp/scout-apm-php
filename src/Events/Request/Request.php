<?php

declare(strict_types=1);

namespace Scoutapm\Events\Request;

use Exception;
use Scoutapm\Connector\Command;
use Scoutapm\Connector\CommandWithChildren;
use Scoutapm\Events\Span\Span;
use Scoutapm\Events\Tag\TagRequest;
use Scoutapm\Helper\Backtrace;
use Scoutapm\Helper\MemoryUsage;
use Scoutapm\Helper\Timer;

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

    /** @var MemoryUsage */
    private $startMemory;

    /** @throws Exception */
    public function __construct()
    {
        $this->id = RequestId::new();

        $this->timer       = new Timer();
        $this->startMemory = MemoryUsage::record();

        $this->currentCommand = $this;
    }

    public function stopIfRunning() : void
    {
        if ($this->timer->getStop() !== null) {
            return;
        }

        $this->stop();
    }

    public function stop(?float $overrideTimestamp = null) : void
    {
        $this->timer->stop($overrideTimestamp);

        $this->tag('memory_delta', MemoryUsage::record()->usedDifferenceInMegabytes($this->startMemory));
    }

    /** @throws Exception */
    public function startSpan(string $operation, ?float $overrideTimestamp = null) : Span
    {
        $span = new Span($this->currentCommand, $operation, $this->id, $overrideTimestamp);

        $this->currentCommand->appendChild($span);

        $this->currentCommand = $span;

        return $span;
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
        if (! $command instanceof Span) {
            $this->stop($overrideTimestamp);

            return;
        }

        $command->stop($overrideTimestamp);

        if ($command->duration() > self::STACK_TRACE_THRESHOLD_SECONDS) {
            $command->tag('stack', Backtrace::capture());
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
     * @internal
     * @deprecated
     *
     * @return Command[]|array<int, Command>
     *
     * @todo remove
     */
    public function getEvents() : array
    {
        return $this->children;
    }
}
