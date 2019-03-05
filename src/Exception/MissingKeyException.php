<?php

namespace Scoutapm\Exception;

class MissingKeyException extends \Exception
{
    public function __construct(string $message = '', int $code = 0, Throwable $previous = null)
    {
        parent::__construct(sprintf('No key set in agent config.', $message), $code, $previous);
    }
}
