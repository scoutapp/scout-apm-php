<?php

declare(strict_types=1);

namespace Scoutapm\Events;

use DateTime;
use DateTimeZone;
use Scoutapm\Agent;
use function sprintf;

class TagSpan extends Tag
{
    /** @var string */
    protected $spanId;

    public function __construct(Agent $agent, string $tag, string $value, string $requestId, string $spanId, ?float $timestamp = null)
    {
        parent::__construct($agent, $tag, $value, $requestId, $timestamp);
        $this->spanId = $spanId;
    }

    /**
     * @return array<string, array<string, (string|array|null)>>
     */
    public function jsonSerialize() : array
    {
        // Format the timestamp
        $timestamp = DateTime::createFromFormat('U.u', sprintf('%.6F', $this->timestamp));
        $timestamp->setTimeZone(new DateTimeZone('UTC'));
        $timestamp = $timestamp->format('Y-m-d\TH:i:s.u\Z');

        return [
            [
                'TagSpan' => [
                    'request_id' => $this->requestId,
                    'span_id' => $this->spanId,
                    'tag' => $this->tag,
                    'value' => $this->value,
                    'timestamp' => $timestamp,
                ],
            ],
        ];
    }
}
