<?php

namespace Scoutapm\Events;

use Ramsey\Uuid\Uuid;

class TagSpan extends Event implements \JsonSerializable
{
    private $request_id;

    private $span_id;

    private $tag;

    private $value;

    public function __construct(string $tag, string $value, string $requestId, string $spanId, float $timestamp = null)
    {
        $this->id = Uuid::uuid4()->toString();

        $this->request_id = $requestId;
        $this->span_id = $spanId;
        $this->tag = $tag;
        $this->value = $value;

        $dt = \DateTime::createFromFormat('U.u', sprintf('%.6F', $timestamp ?? microtime(true)));
        $dt->setTimeZone(new \DateTimeZone('UTC'));
        $this->timestamp = $dt->format('Y-m-d\TH:i:s.u\Z');
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

    public function getSpanData() : array
    {
        return [
            'TagSpan' => [
                'request_id' => $this->getRequestId(),
                'span_id' => $this->getSpanId(),
                'tag' => $this->getTag(),
                'value' => $this->getValue(),
                'timestamp' => $this->getTimestamp(),
            ]
        ];
    }

    public function getArrays() : array
    {
        return [$this->getSpanData()];
    }

    public function jsonSerialize() : array
    {
        return $this->getSpanData();
    }
}
