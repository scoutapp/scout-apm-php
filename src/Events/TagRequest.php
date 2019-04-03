<?php

namespace Scoutapm\Events;

class TagRequest extends Tag
{
    public function __construct(string $tag, string $value, float $timestamp = null)
    {
        $this->name = 'TagRequest';
        parent::__construct($tag, $value, $timestamp);
    }
}
