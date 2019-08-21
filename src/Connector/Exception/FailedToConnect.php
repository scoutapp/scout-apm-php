<?php

declare(strict_types=1);

namespace Scoutapm\Connector\Exception;

use RuntimeException;

final class FailedToConnect extends RuntimeException
{
    public static function fromSocketPathAndPrevious(string $socketPath, \Throwable $previous) : self
    {
        return new self(sprintf(
            'Failed to connect to socket on path "%s", previous message: %s',
            $socketPath,
            $previous->getMessage()
        ));
    }
}
