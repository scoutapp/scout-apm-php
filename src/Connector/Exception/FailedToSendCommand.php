<?php

declare(strict_types=1);

namespace Scoutapm\Connector\Exception;

use Psr\Log\LogLevel;
use RuntimeException;
use Scoutapm\Connector\Command;
use Scoutapm\Connector\ConnectionAddress;
use Throwable;

use function get_class;
use function json_last_error_msg;
use function socket_last_error;
use function socket_strerror;
use function sprintf;

/** @psalm-type ValidLogLevel = LogLevel::* */
final class FailedToSendCommand extends RuntimeException
{
    /**
     * @var string
     * @psalm-var ValidLogLevel
     */
    private $logLevel;

    /** @psalm-param ValidLogLevel $logLevel */
    public function __construct(string $logLevel, string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->logLevel = $logLevel;
    }

    /** @psalm-return ValidLogLevel */
    public function logLevel(): string
    {
        return $this->logLevel;
    }

    public static function unableToSerializeCommand(Command $attemptedCommand): self
    {
        return new self(
            LogLevel::CRITICAL,
            sprintf(
                'Failed to serialize command of type %s to JSON. Last JSON error: %s',
                get_class($attemptedCommand),
                json_last_error_msg()
            )
        );
    }

    /** @param resource $socketResource */
    public static function writingMessageSizeToSocket(Command $attemptedCommand, $socketResource, ConnectionAddress $connectionAddress): self
    {
        $socketErrorNumber = socket_last_error($socketResource);

        return new self(
            LogLevel::ERROR,
            sprintf(
                'Failed to write message size for %s - error %d (%s). Address was: %s',
                get_class($attemptedCommand),
                $socketErrorNumber,
                socket_strerror($socketErrorNumber),
                $connectionAddress->toString()
            )
        );
    }

    /** @param resource $socketResource */
    public static function writingMessageContentToSocket(Command $attemptedCommand, $socketResource, ConnectionAddress $connectionAddress): self
    {
        $socketErrorNumber = socket_last_error($socketResource);

        return new self(
            LogLevel::ERROR,
            sprintf(
                'Failed to write message content for %s - error %d (%s). Address was: %s',
                get_class($attemptedCommand),
                $socketErrorNumber,
                socket_strerror($socketErrorNumber),
                $connectionAddress->toString()
            )
        );
    }

    /** @param resource $socketResource */
    public static function readingResponseSizeFromSocket(Command $attemptedCommand, $socketResource, ConnectionAddress $connectionAddress): self
    {
        $socketErrorNumber = socket_last_error($socketResource);

        return new self(
            LogLevel::ERROR,
            sprintf(
                'Failed to read response size for %s - error %d (%s). Address was: %s',
                get_class($attemptedCommand),
                $socketErrorNumber,
                socket_strerror($socketErrorNumber),
                $connectionAddress->toString()
            )
        );
    }

    public static function fromEmptyResponseSize(Command $attemptedCommand, ConnectionAddress $connectionAddress): self
    {
        return new self(
            LogLevel::ERROR,
            sprintf(
                'Response size was not returned for %s (empty string). Address was: %s',
                get_class($attemptedCommand),
                $connectionAddress->toString()
            )
        );
    }

    public static function fromFailedResponseUnpack(Command $attemptedCommand, ConnectionAddress $connectionAddress): self
    {
        return new self(
            LogLevel::ERROR,
            sprintf(
                'Response length could not be unpacked for %s (maybe invalid format?). Address was: %s',
                get_class($attemptedCommand),
                $connectionAddress->toString()
            )
        );
    }

    public static function fromTooLargeResponseLength(
        int $responseLengthReturned,
        int $responseLengthLimit,
        Command $attemptedCommand,
        ConnectionAddress $connectionAddress
    ): self {
        return new self(
            LogLevel::NOTICE,
            sprintf(
                'Response length returned (%d) exceeded our limit for reading (%d) for %s. Address was: %s',
                $responseLengthReturned,
                $responseLengthLimit,
                get_class($attemptedCommand),
                $connectionAddress->toString()
            )
        );
    }

    /** @param resource $socketResource */
    public static function readingResponseContentFromSocket(
        Command $attemptedCommand,
        int $responseLength,
        int $bytesAttempted,
        int $bytesReadSoFar,
        $socketResource,
        ConnectionAddress $connectionAddress
    ): self {
        $socketErrorNumber = socket_last_error($socketResource);

        return new self(
            LogLevel::ERROR,
            sprintf(
                'Failed to read response content for %s - read %d of %d bytes, tried to read %d bytes more - error %d (%s). Address was: %s',
                get_class($attemptedCommand),
                $bytesReadSoFar,
                $responseLength,
                $bytesAttempted,
                $socketErrorNumber,
                socket_strerror($socketErrorNumber),
                $connectionAddress->toString()
            )
        );
    }
}
