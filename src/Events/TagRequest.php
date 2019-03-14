<?php

namespace Scoutapm\Events;

class TagRequest extends Event
{
    private $request_id;

    private $span_id;

    private $tag;

    private $value;

    public function __construct(string $tag, string $value, string $requestId, string $spanId, float $timestamp = null)
    {
        parent::__construct();

        $this->request_id = $requestId;
        $this->span_id = $spanId;
        $this->tag = $tag;
        $this->value = $value;
    }

    public function getRequestId() : string
    {
        return $this->request_id;
    }

    public function getSpanId() : string
    {
        return $this->span_id;
    }

    public function getTag() : ?string
    {
        return $this->tag;
    }

    public function getValue() : string
    {
        return $this->value;
    }

    public function getArrays()
    {
        return [
            ['TagRequest' => [
                'request_id' => $this->getRequestId(),
                'span_id' => $this->getSpanId(),
                'tag' => $this->getTag(),
                'value' => $this->getValue(),
                'timestamp' => $this->getTimestamp(),
            ]]
        ];
    }
}
