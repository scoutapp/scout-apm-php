<?php

declare(strict_types=1);

namespace Scoutapm\Exception\Request;

use Exception;
use Throwable;
use function sprintf;

class DuplicateRequestName extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(sprintf('A request with the name %s is already registered.', $message), $code, $previous);
    }
}
