<?php

declare(strict_types=1);

namespace Scoutapm\Connector\Exception;

use RuntimeException;
use Scoutapm\Connector\ConnectionAddress;
use Throwable;

use function sprintf;

final class FailedToConnect extends RuntimeException
{
    public static function fromSocketPathAndPrevious(ConnectionAddress $connectionAddress, Throwable $previous): self
    {
        return new self(sprintf(
            'Failed to connect to socket on address "%s", previous message: %s',
            $connectionAddress->toString(),
            $previous->getMessage()
        ));
    }
}
