<?php

namespace Scoutapm\Events;

class TagSpan extends Tag
{
    protected $spanId;

    public function setSpanId($spanId)
    {
        $this->spanId = $spanId;
    }

    public function getArrays() : array
    {
        return [
            ['TagSpan' => [
                'request_id' => $this->requestId,
                'span_id' => $this->spanId,
                'tag' => $this->tag,
                'value' => $this->value,
                'timestamp' => $this->timestamp,
            ]]
        ];
    }
}
