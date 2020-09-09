<?php

declare(strict_types=1);

namespace Scoutapm\Connector\Exception;

use RuntimeException;
use Scoutapm\Connector\Command;
use Scoutapm\Connector\ConnectionAddress;
use function get_class;
use function socket_last_error;
use function socket_strerror;
use function sprintf;

final class FailedToSendCommand extends RuntimeException
{
    /** @param resource $socketResource */
    public static function writingMessageSizeToSocket(Command $attemptedCommand, $socketResource, ConnectionAddress $connectionAddress) : self
    {
        $socketErrorNumber = socket_last_error($socketResource);

        return new self(sprintf(
            'Failed to write message size for %s - error %d (%s). Address was: %s',
            get_class($attemptedCommand),
            $socketErrorNumber,
            socket_strerror($socketErrorNumber),
            $connectionAddress->toString()
        ));
    }

    /** @param resource $socketResource */
    public static function writingMessageContentToSocket(Command $attemptedCommand, $socketResource, ConnectionAddress $connectionAddress) : self
    {
        $socketErrorNumber = socket_last_error($socketResource);

        return new self(sprintf(
            'Failed to write message content for %s - error %d (%s). Address was: %s',
            get_class($attemptedCommand),
            $socketErrorNumber,
            socket_strerror($socketErrorNumber),
            $connectionAddress->toString()
        ));
    }

    /** @param resource $socketResource */
    public static function readingResponseSizeFromSocket(Command $attemptedCommand, $socketResource, ConnectionAddress $connectionAddress) : self
    {
        $socketErrorNumber = socket_last_error($socketResource);

        return new self(sprintf(
            'Failed to read response size for %s - error %d (%s). Address was: %s',
            get_class($attemptedCommand),
            $socketErrorNumber,
            socket_strerror($socketErrorNumber),
            $connectionAddress->toString()
        ));
    }

    public static function fromEmptyResponseSize(Command $attemptedCommand, ConnectionAddress $connectionAddress) : self
    {
        return new self(sprintf(
            'Response size was not returned for %s (empty string). Address was: %s',
            get_class($attemptedCommand),
            $connectionAddress->toString()
        ));
    }

    /** @param resource $socketResource */
    public static function readingResponseContentFromSocket(Command $attemptedCommand, $socketResource, ConnectionAddress $connectionAddress) : self
    {
        $socketErrorNumber = socket_last_error($socketResource);

        return new self(sprintf(
            'Failed to read response content for %s - error %d (%s). Address was: %s',
            get_class($attemptedCommand),
            $socketErrorNumber,
            socket_strerror($socketErrorNumber),
            $connectionAddress->toString()
        ));
    }
}
