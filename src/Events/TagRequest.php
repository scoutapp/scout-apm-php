<?php

namespace Scoutapm\Events;

class TagRequest extends Tag
{
    public function __construct(\Scoutapm\Agent $agent, string $tag, string $value, float $timestamp = null)
    {
        $this->name = 'TagRequest';
        parent::__construct($agent, $tag, $value, $timestamp);
    }
}
