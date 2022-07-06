<?php

declare(strict_types=1);

namespace Scoutapm\Events\Tag;

use DateTime;
use DateTimeZone;

use function sprintf;

/**
 * Naming perhaps is a little off, it perhaps would've made more sense to name this `RequestTag`, but the JSON expects
 * imperative tense, so read this as "tag the request" rather than "this is a request's tag". This naming is kept to be
 * consistent with the payload that the core agent expects.
 *
 * @internal
 */
class TagRequest extends Tag
{
    /**
     * @return string[][][]|array[][][]|bool[][][]|null[][][]
     * @psalm-return list<
     *      array{
     *          TagRequest: array{
     *              request_id: string,
     *              tag: string,
     *              value: mixed,
     *              timestamp: string
     *          }
     *      }
     * >
     */
    public function jsonSerialize(): array
    {
        // Format the timestamp
        $timestamp = DateTime::createFromFormat('U.u', sprintf('%.6F', $this->timestamp));
        $timestamp->setTimeZone(new DateTimeZone('UTC'));
        $timestamp = $timestamp->format('Y-m-d\TH:i:s.u\Z');

        return [
            [
                'TagRequest' => [
                    'request_id' => $this->requestId->toString(),
                    'tag' => $this->tag,
                    'value' => $this->value,
                    'timestamp' => $timestamp,
                ],
            ],
        ];
    }
}
