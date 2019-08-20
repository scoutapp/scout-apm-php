<?php

declare(strict_types=1);

namespace Scoutapm\Events\Tag;

use DateTime;
use DateTimeZone;
use Scoutapm\Events\Request\RequestId;
use Scoutapm\Events\Span\SpanId;
use function sprintf;

/** @internal */
class TagSpan extends Tag
{
    /** @var SpanId */
    protected $spanId;

    /** @param mixed $value */
    public function __construct(string $tag, $value, RequestId $requestId, SpanId $spanId, ?float $timestamp = null)
    {
        parent::__construct($tag, $value, $requestId, $timestamp);
        $this->spanId = $spanId;
    }

    /**
     * @return array<int, array<string, (string|array|bool|null)>>
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
                    'request_id' => $this->requestId->toString(),
                    'span_id' => $this->spanId->toString(),
                    'tag' => $this->tag,
                    'value' => $this->value,
                    'timestamp' => $timestamp,
                ],
            ],
        ];
    }
}
