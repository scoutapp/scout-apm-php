<?php

namespace Scoutapm\Events;

use Scoutapm\Helper\Timer;

class Span extends Event implements \JsonSerializable
{
    private $requestId;

    private $parentId;

    private $name;

    private $timer;

    private $tags;

    public function __construct(\Scoutapm\Agent $agent, string $name, $requestId, $override = null)
    {
        parent::__construct($agent);

        $this->name = $name;
        $this->requestId = $requestId;

        $this->tags = [];

        $this->timer = new Timer($override);
    }

    // Do not call this directly - use Request#stopSpan() or Agent#stopSpan()
    // to correctly handle bookkeeping
    public function stop($override = null)
    {
        $this->timer->stop($override);
    }


    public function tag($tag, $value)
    {
        $tagSpan = new TagSpan($this->agent, $tag, $value, $this->requestId, $this->id);
        $this->tags[] = $tagSpan;
    }

    public function setParentId(string $parentId)
    {
        $this->parentId = $parentId;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function getStartTime()
    {
        return $this->timer->getStart();
    }

    public function getStopTime()
    {
        return $this->timer->getStop();
    }

    public function getTags()
    {
        return $this->tags;
    }

    public function jsonSerialize()
    {
        $commands = [];
        $commands[] = ['StartSpan' => [
            'request_id' => $this->requestId,
            'span_id' => $this->id,
            'parent_id' => $this->parentId,
            'operation' => $this->name,
            'timestamp' => $this->getStartTime(),
        ]];

        foreach ($this->tags as $tag) {
            $array = $tag->jsonSerialize();

            foreach ($array as $value) {
                $commands[] = $value;
            }
        }

        $commands[] = ['StopSpan' => [
            'request_id' => $this->requestId,
            'span_id' => $this->id,
            'timestamp' => $this->getStopTime(),
        ]];

        return $commands;
    }
}
