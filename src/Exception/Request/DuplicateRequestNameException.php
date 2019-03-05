<?php

namespace Scoutapm\Exception\Request;

class DuplicateRequestNameException extends \Exception
{
    public function __construct(string $message = '', int $code = 0, Throwable $previous = null)
    {
        parent::__construct(sprintf('A request with the name %s is already registered.', $message), $code, $previous);
    }
}
