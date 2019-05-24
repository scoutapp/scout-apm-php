<?php

namespace Scoutapm\Events;

class TagSpan extends Tag
{
    protected $spanId;

    public function __construct(\Scoutapm\Agent $agent, string $tag, string $value, float $timestamp = null)
    {
        parent::__construct($agent, $tag, $value, $timestamp);
    }

    public function setSpanId($spanId)
    {
        $this->spanId = $spanId;
    }

    public function jsonSerialize()
    {
        // Format the timestamp
        $timestamp = \DateTime::createFromFormat('U.u', sprintf('%.6F', $this->timestamp));
        $timestamp->setTimeZone(new \DateTimeZone('UTC'));
        $timestamp = $timestamp->format('Y-m-d\TH:i:s.u\Z');

        return [
            ['TagRequest' => [
                'request_id' => $this->requestId,
                'span_id' => $this->spanId,
                'tag' => $this->tag,
                'value' => $this->value,
                'timestamp' => $timestamp,
            ]]
        ];
    }
}
