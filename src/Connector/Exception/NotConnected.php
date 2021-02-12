<?php

declare(strict_types=1);

namespace Scoutapm\Connector\Exception;

use RuntimeException;
use Scoutapm\Connector\ConnectionAddress;

use function sprintf;

final class NotConnected extends RuntimeException
{
    public static function fromSocketPath(ConnectionAddress $connectionAddress): self
    {
        return new self(sprintf(
            'Connector has not been connected to address "%s"',
            $connectionAddress->toString()
        ));
    }
}
