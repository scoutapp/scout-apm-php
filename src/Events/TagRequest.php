<?php

namespace Scoutapm\Events;

class TagRequest extends Tag
{
    public function __construct(\Scoutapm\Agent $agent, string $tag, string $value, float $timestamp = null)
    {
        parent::__construct($agent, $tag, $value, $timestamp);
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
                'tag' => $this->tag,
                'value' => $this->value,
                'timestamp' => $timestamp,
            ]]
        ];
    }
}
