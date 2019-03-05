<?php

namespace Scoutapm\Exception\Request;

class UnknownRequestException extends \Exception
{
    public function __construct(string $message = '', int $code = 0, Throwable $previous = null)
    {
        parent::__construct(sprintf('The request "%s" is not registered.', $message), $code, $previous);
    }
}
