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

    /** @var mixed */
    protected $value;

    /** @var float */
    protected $timestamp;

    /**
     * Value can be any jsonable structure
     *
     * @param mixed $value
     */
    public function __construct(Agent $agent, string $tag, $value, string $requestId, ?float $timestamp = null)
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
