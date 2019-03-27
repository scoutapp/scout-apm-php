<?php

namespace Scoutapm\Events;

class TagSpan extends Tag
{
    protected $spanId;

    public function setSpanId($spanId)
    {
        $this->spanId = $spanId;
    }

    public function getEventArray(array &$parents): array
    {
        $currentParent = end($parents);
        $this->setSpanId($currentParent->getId());

        $timestamp = \DateTime::createFromFormat('U.u', sprintf('%.6F', $this->timestamp));
        $timestamp->setTimeZone(new \DateTimeZone('UTC'));

        return [
            ['TagSpan' => [
                'request_id' => $this->requestId,
                'span_id' => $this->spanId,
                'tag' => $this->tag,
                'value' => $this->value,
                'timestamp' => $timestamp->format('Y-m-d\TH:i:s.u\Z'),
            ]]
        ];
    }
}
