<?php

declare(strict_types=1);

namespace Scoutapm\Events;

use Scoutapm\Agent;
use function microtime;

class Tag extends Event
{
    /** @var string */
    protected $requestId;

    /** @var string */
    protected $tag;

    /** @var string */
    protected $value;

    /** @var float|null */
    protected $timestamp;

    /**
     * Value can be any jsonable structure
     */
    public function __construct(Agent $agent, string $tag, string $value, string $requestId, ?float $timestamp = null)
    {
        parent::__construct($agent);

        if ($timestamp === null) {
            $timestamp = microtime(true);
        }

        $this->tag       = $tag;
        $this->value     = $value;
        $this->requestId = $requestId;
        $this->timestamp = $timestamp;
    }

    /**
     * Get the 'key' portion of this Tag
     */
    public function getTag() : string
    {
        return $this->tag;
    }

    /**
     * Get the 'value' portion of this Tag
     */
    public function getValue() : string
    {
        return $this->value;
    }
}
