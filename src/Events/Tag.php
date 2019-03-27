<?php

namespace Scoutapm\Events;

class Tag extends Event
{
    protected $requestId;

    protected $tag;

    protected $value;

    protected $timestamp;

    protected $name;

    protected $extraAttributes = [];

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

    public function setExtraAttributes(array $attributes)
    {
        $this->extraAttributes = $attributes;
    }

    public function getEventArray(array &$parents) : array
    {
        $timestamp = \DateTime::createFromFormat('U.u', sprintf('%.6F', $this->timestamp));
        $timestamp->setTimeZone(new \DateTimeZone('UTC'));

        return [
            [$this->name => [
                'request_id' => $this->requestId,
                'tag' => $this->tag,
                'value' => $this->value,
                'timestamp' => $timestamp->format('Y-m-d\TH:i:s.u\Z'),
            ] + $this->extraAttributes]
        ];
    }
}
