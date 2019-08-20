<?php

declare(strict_types=1);

namespace Scoutapm\Events\Exception;

use Exception;
use Ramsey\Uuid\UuidInterface;
use function sprintf;

class SpanHasNotBeenStarted extends Exception
{
    public static function fromRequest(UuidInterface $requestId) : self
    {
        return new self(sprintf(
            'Can\'t stop a timer which isn\'t started in request %s',
            $requestId->toString()
        ));
    }
}
