<?php

declare(strict_types=1);

namespace Scoutapm\Exception;

use Exception;
use function sprintf;

class MissingAppName extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(sprintf('No app name set in agent config.', $message), $code, $previous);
    }
}
