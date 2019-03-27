<?php

namespace Scoutapm\Events;

abstract class Tag extends Event
{
    protected $requestId;

    protected $tag;

    protected $value;

    protected $timestamp;

    public function __construct(string $tag, string $value, float $timestamp = null)
    {
        parent::__construct();

        if ($timestamp === null) {
            $timestamp = microtime(true);
        }

        $this->tag = $tag;
        $this->value = $value;
        $this->timestamp = $timestamp;

    }

    public function setRequestId($requestId)
    {
        $this->requestId = $requestId;
    }
}
