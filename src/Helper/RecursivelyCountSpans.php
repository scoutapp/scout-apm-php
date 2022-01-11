<?php

declare(strict_types=1);

namespace Scoutapm\Helper;

use Scoutapm\Connector\Command;
use Scoutapm\Events\Span\Span;

use function array_reduce;

/** @internal */
final class RecursivelyCountSpans
{
    /** @param Command[] $commands */
    public static function forCommands(array $commands): int
    {
        return array_reduce(
            $commands,
            static function (int $carry, Command $item): int {
                if (! $item instanceof Span) {
                    return $carry;
                }

                return $carry + 1 + $item->collectedSpans();
            },
            0
        );
    }
}
