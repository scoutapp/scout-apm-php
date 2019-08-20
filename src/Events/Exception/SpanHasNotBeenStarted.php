<?php

declare(strict_types=1);

namespace Scoutapm\Events\Exception;

use Exception;
use Scoutapm\Events\Request\RequestId;
use function sprintf;

class SpanHasNotBeenStarted extends Exception
{
    public static function fromRequest(RequestId $requestId) : self
    {
        return new self(sprintf(
            'Can\'t stop a timer which isn\'t started in request %s',
            $requestId->toString()
        ));
    }
}
