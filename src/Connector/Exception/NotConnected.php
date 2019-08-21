<?php

declare(strict_types=1);

namespace Scoutapm\Connector\Exception;

use RuntimeException;
use function sprintf;

final class NotConnected extends RuntimeException
{
    public static function fromSocketPath(string $socketPath) : self
    {
        return new self(sprintf(
            'Connector has not been connected to socket path "%s"',
            $socketPath
        ));
    }
}
