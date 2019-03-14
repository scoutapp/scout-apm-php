<?php

namespace Scoutapm\Events;

abstract class Tag extends Event
{
    protected $requestId;

    protected $tag;

    protected $value;

    public function __construct(string $tag, string $value, float $timestamp = null)
    {
        parent::__construct();

        $this->tag = $tag;
        $this->value = $value;

        if ($timestamp !== null) {
            $this->timestamp = $timestamp;
        }
    }

    public function setRequestId($requestId)
    {
        $this->requestId = $requestId;
    }

    abstract public function getArrays() : array;
}
