<?php

namespace Scoutapm\Exception;

class MissingAppNameException extends \Exception
{
    public function __construct(string $message = '', int $code = 0, Throwable $previous = null)
    {
        parent::__construct(sprintf('No app name set in agent config.', $message), $code, $previous);
    }
}
