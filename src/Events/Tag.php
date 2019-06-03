<?php

namespace Scoutapm\Events;

class Tag extends Event
{
    protected $requestId;

    protected $tag;

    protected $value;

    protected $timestamp;

    /**
     * Value can be any jsonable structure
     */
    public function __construct(\Scoutapm\Agent $agent, string $tag, $value, string $requestId, float $timestamp = null)
    {
        parent::__construct($agent);

        if ($timestamp === null) {
            $timestamp = microtime(true);
        }

        $this->tag = $tag;
        $this->value = $value;
        $this->requestId = $requestId;
        $this->timestamp = $timestamp;
    }

    /**
     * Get the 'key' portion of this Tag
     *
     * @return string
     */
    public function getTag()
    {
        return $this->tag;
    }
    
    /**
     * Get the 'value' portion of this Tag
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}
