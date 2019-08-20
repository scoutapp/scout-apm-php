<?php

declare(strict_types=1);

namespace Scoutapm\Exception\Request;

use Exception;
use Throwable;
use function sprintf;

class UnknownRequest extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(sprintf('The request "%s" is not registered.', $message), $code, $previous);
    }
}
