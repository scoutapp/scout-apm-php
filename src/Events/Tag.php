<?php

namespace Scoutapm\Events;

class Tag extends Event
{
    protected $requestId;

    protected $tag;

    protected $value;

    protected $timestamp;

    protected $name;

    protected $extraAttributes = [];

    /**
     * Value can be any jsonable structure
     */
    public function __construct(\Scoutapm\Agent $agent, string $tag, $value, float $timestamp = null)
    {
        parent::__construct($agent);

        if ($timestamp === null) {
            $timestamp = microtime(true);
        }

        $this->tag = $tag;
        $this->value = $value;
        $this->timestamp = $timestamp;
    }

    public function setRequestId($requestId)
    {
        $this->requestId = $requestId;
    }

    public function setExtraAttributes(array $attributes)
    {
        $this->extraAttributes = $attributes;
    }

    public function getEventArray(array &$parents) : array
    {
        $timestamp = \DateTime::createFromFormat('U.u', sprintf('%.6F', $this->timestamp));
        $timestamp->setTimeZone(new \DateTimeZone('UTC'));

        return [
            [$this->name => [
                'request_id' => $this->requestId,
                'tag' => $this->tag,
                'value' => $this->value,
                'timestamp' => $timestamp->format('Y-m-d\TH:i:s.u\Z'),
            ] + $this->extraAttributes]
        ];
    }

    /**
     * Get the 'key' portion of this Tag
     *
     * @return string
     */
    public function getTag()
    {
        return $this->tag;
    }
    
    /**
     * Get the 'value' portion of this Tag
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
    
}
