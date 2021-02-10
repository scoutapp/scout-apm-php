<?php

declare(strict_types=1);

namespace Scoutapm\Events\Request\Exception;

use RuntimeException;

use function sprintf;

class SpanLimitReached extends RuntimeException
{
    public static function forOperation(string $attemptedOperation, int $limit): self
    {
        return new self(sprintf(
            'Span limit of %d has been reached trying to start span for "%s"',
            $limit,
            $attemptedOperation
        ));
    }
}
