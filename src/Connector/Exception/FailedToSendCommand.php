<?php

declare(strict_types=1);

namespace Scoutapm\Connector\Exception;

use RuntimeException;
use Scoutapm\Connector\Command;
use function get_class;
use function socket_last_error;
use function socket_strerror;
use function sprintf;

final class FailedToSendCommand extends RuntimeException
{
    /** @param resource $socketResource */
    public static function writingMessageSizeToSocket(Command $attemptedCommand, $socketResource, string $socketPath) : self
    {
        $socketErrorNumber = socket_last_error($socketResource);

        return new self(sprintf(
            'Failed to write message size for %s - error %d (%s). Socket path was: %s',
            get_class($attemptedCommand),
            $socketErrorNumber,
            socket_strerror($socketErrorNumber),
            $socketPath
        ));
    }

    /** @param resource $socketResource */
    public static function writingMessageContentToSocket(Command $attemptedCommand, $socketResource, string $socketPath) : self
    {
        $socketErrorNumber = socket_last_error($socketResource);

        return new self(sprintf(
            'Failed to write message content for %s - error %d (%s). Socket path was: %s',
            get_class($attemptedCommand),
            $socketErrorNumber,
            socket_strerror($socketErrorNumber),
            $socketPath
        ));
    }

    /** @param resource $socketResource */
    public static function readingResponseSizeFromSocket(Command $attemptedCommand, $socketResource, string $socketPath) : self
    {
        $socketErrorNumber = socket_last_error($socketResource);

        return new self(sprintf(
            'Failed to read response size for %s - error %d (%s). Socket path was: %s',
            get_class($attemptedCommand),
            $socketErrorNumber,
            socket_strerror($socketErrorNumber),
            $socketPath
        ));
    }

    public static function fromEmptyResponseSize(Command $attemptedCommand, string $socketPath) : self
    {
        return new self(sprintf(
            'Response size was not returned for %s (empty string). Socket path was: %s',
            get_class($attemptedCommand),
            $socketPath
        ));
    }

    /** @param resource $socketResource */
    public static function readingResponseContentFromSocket(Command $attemptedCommand, $socketResource, string $socketPath) : self
    {
        $socketErrorNumber = socket_last_error($socketResource);

        return new self(sprintf(
            'Failed to read response content for %s - error %d (%s). Socket path was: %s',
            get_class($attemptedCommand),
            $socketErrorNumber,
            socket_strerror($socketErrorNumber),
            $socketPath
        ));
    }
}
