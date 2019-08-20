<?php

declare(strict_types=1);

namespace Scoutapm\Events;

use DateTime;
use DateTimeZone;
use Ramsey\Uuid\UuidInterface;
use function sprintf;

/** @internal */
class TagSpan extends Tag
{
    /** @var UuidInterface */
    protected $spanId;

    /** @param mixed $value */
    public function __construct(string $tag, $value, UuidInterface $requestId, UuidInterface $spanId, ?float $timestamp = null)
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
