<?php

declare(strict_types=1);

namespace Scoutapm\Events\Tag;

use DateTime;
use DateTimeZone;
use function sprintf;

/** @internal */
class RequestTag extends Tag
{
    /** @return array<int, array<string, (string|array|bool|null)>> */
    public function jsonSerialize() : array
    {
        // Format the timestamp
        $timestamp = DateTime::createFromFormat('U.u', sprintf('%.6F', $this->timestamp));
        $timestamp->setTimeZone(new DateTimeZone('UTC'));
        $timestamp = $timestamp->format('Y-m-d\TH:i:s.u\Z');

        return [
            [
                'RequestTag' => [
                    'request_id' => $this->requestId->toString(),
                    'tag' => $this->tag,
                    'value' => $this->value,
                    'timestamp' => $timestamp,
                ],
            ],
        ];
    }
}
