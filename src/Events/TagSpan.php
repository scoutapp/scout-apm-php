<?php

namespace Scoutapm\Events;

class TagSpan extends Tag
{
    protected $spanId;

    public function __construct(string $tag, string $value, float $timestamp = null)
    {
        $this->name = 'TagSpan';
        parent::__construct($tag, $value, $timestamp);
    }

    public function setSpanId($spanId)
    {
        $this->spanId = $spanId;
    }

    public function getEventArray(array &$parents): array
    {
        $currentParent = end($parents);
        $this->setSpanId($currentParent->getId());

        $this->extraAttributes = ['span_id' => $this->spanId];

        return parent::getEventArray($parents);
    }
}
