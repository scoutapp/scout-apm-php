<?php

namespace Scoutapm\Events;

use Ramsey\Uuid\Uuid;
use Scoutapm\Helper\Timer;
use Scoutapm\Events\Span;

class Request extends Event implements \JsonSerializable
{
    private $name;

    private $timer;

    private $summary = [
        'duration'  => 0.0,
    ];

    private $spans = [];

    public function __construct(string $name)
    {
        $this->id = Uuid::uuid4()->toString();
        $timestamp = \DateTime::createFromFormat('U.u', sprintf('%.6F', microtime(true)));
        $timestamp->setTimeZone(new \DateTimeZone('UTC'));
        $this->timestamp = $timestamp->format('Y-m-d\TH:i:s.u\Z');

        $this->setRequestName($name);
        $this->timer = new Timer();
    }

    public function start($override = null) : void
    {
        $this->timer->start($override);
    }

    public function stop(int $duration = null)
    {
        $this->timer->stop();

        $this->summary['duration']  = $duration ?? round($this->timer->getDuration(), 3);
    }

    public function setRequestName(string $name)
    {
        $this->name = $name;
    }

    public function getRequestName() : string
    {
        return $this->name;
    }

    public function getSummary() : array
    {
        return $this->summary;
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

    public function getStartData() : array
    {
        return [
            'StartRequest' => [
                'request_id' => $this->getId(),
                'timestamp' => $this->timer->getStart(),
            ]
        ];
    }

    public function getFinishData() : array
    {
        return [
            'FinishRequest' => [
                'request_id' => $this->getId(),
                'timestamp' => $this->timer->getStop(),
            ]
        ];
    }

    public function jsonSerialize() : array
    {
        $output = [$this->getStartData()];

        $spans = $this->getSpans();
        foreach ($spans as $span) {
            $arr = $span->getArrays();
    
            foreach ($arr as $value) {
                $output[] = $value;
            }
        }

        $output[] = $this->getFinishData();


        return $output;
    }
}
