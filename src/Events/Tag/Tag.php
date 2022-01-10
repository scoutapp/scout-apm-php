<?php

declare(strict_types=1);

namespace Scoutapm\Events\Tag;

use Scoutapm\Connector\Command;
use Scoutapm\Events\Request\RequestId;

use function microtime;

/** @internal */
abstract class Tag implements Command
{
    public const TAG_STACK_TRACE      = 'stack';
    public const TAG_ARGUMENTS        = 'args';
    public const TAG_MEMORY_DELTA     = 'memory_delta';
    public const TAG_REQUEST_PATH     = 'path';
    public const TAG_QUEUE_TIME       = 'scout.queue_time_ns';
    public const TAG_REACHED_SPAN_CAP = 'scout.reached_span_cap';
    public const TAG_URI              = 'uri';

    /** @var RequestId */
    protected $requestId;

    /** @var string */
    protected $tag;

    /** @var mixed */
    protected $value;

    /** @var float */
    protected $timestamp;

    /**
     * Value can be any jsonable structure
     *
     * @param mixed $value
     */
    public function __construct(string $tag, $value, RequestId $requestId, ?float $timestamp = null)
    {
        if ($timestamp === null) {
            $timestamp = microtime(true);
        }

        $this->tag       = $tag;
        $this->value     = $value;
        $this->requestId = $requestId;
        $this->timestamp = $timestamp;
    }

    public function cleanUp(): void
    {
        unset($this->tag, $this->value, $this->requestId, $this->timestamp);
    }

    /**
     * Get the 'key' portion of this Tag
     */
    public function getTag(): string
    {
        return $this->tag;
    }

    /**
     * Get the 'value' portion of this Tag
     */
    public function getValue(): string
    {
        return $this->value;
    }
}
