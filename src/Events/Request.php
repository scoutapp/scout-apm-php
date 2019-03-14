<?php

namespace Scoutapm\Events;

use Ramsey\Uuid\Uuid;
use Scoutapm\Helper\Timer;

class Request extends Event implements \JsonSerializable
{
    private $name;

    private $timer;

    private $spans = [];

    public function __construct(string $name)
    {
        parent::__construct();

        $this->setRequestName($name);
        $this->timer = new Timer();
    }

    public function start($override = null) : void
    {
        $this->timer->start($override);
    }

    public function stop()
    {
        $this->timer->stop();
    }

    public function setRequestName(string $name)
    {
        $this->name = $name;
    }

    public function getRequestName() : string
    {
        return $this->name;
    }

    public function setSpan(Span $span)
    {
        $name = $span->getName();
        $this->spans[$name] = $span;
    }

    public function getSpan(string $name): Span
    {
        return $this->spans[$name];
    }

    public function getSpans(): array
    {
        return $this->spans;
    }

    public function getFirstSpan() : Span
    {
        return reset($this->spans);
    }

    public function tagSpan(TagSpan $tagSpan)
    {
        $this->spans[$tagSpan->getId()] = $tagSpan;
    }

    public function jsonSerialize() : array
    {
        $output = [
            [
                'StartRequest' => [
                    'request_id' => $this->getId(),
                    'timestamp' => $this->timer->getStart(),
                ]
            ],
        ];

        $spans = $this->getSpans();
        foreach ($spans as $span) {
            $arr = $span->getArrays();
    
            foreach ($arr as $value) {
                $output[] = $value;
            }
        }

        $output[] =             [
            'FinishRequest' => [
                'request_id' => $this->getId(),
                'timestamp' => $this->timer->getStop(),
            ]
            ];


        return $output;
    }
}
