<?php

declare(strict_types=1);

namespace Scoutapm\Exception\Timer;

use Exception;
use Throwable;

class NotStarted extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct('Can\'t stop a timer which isn\'t started.', $code, $previous);
    }
}
