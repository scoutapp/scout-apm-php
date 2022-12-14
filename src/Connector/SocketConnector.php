<?php

declare(strict_types=1);

namespace Scoutapm\Connector;

use ErrorException;
use Psr\Log\LoggerInterface;
use Scoutapm\Connector\Exception\FailedToConnect;
use Scoutapm\Connector\Exception\NotConnected;
use Throwable;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function is_array;
use function json_encode;
use function pack;
use function register_shutdown_function;
use function restore_error_handler;
use function set_error_handler;
use function socket_clear_error;
use function socket_close;
use function socket_connect;
use function socket_create;
use function socket_read;
use function socket_send;
use function socket_shutdown;
use function sprintf;
use function strlen;
use function unpack;

use const AF_INET;
use const AF_UNIX;
use const E_NOTICE;
use const E_STRICT;
use const E_WARNING;
use const SOCK_STREAM;

/** @internal */
final class SocketConnector implements Connector
{
    /**
     * Read a maximum of 10mb; practically this should not happen, since the response is mostly composed of small JSON
     * objects per successful StartSpan/StopSpan etc, so we have a limit applied to prevent allocating too much memory
     */
    private const MAXIMUM_RESPONSE_LENGTH_TO_READ = 10000000;

    /**
     * Note: this should be `\Socket|resource|null` but Psalm does not support \Socket properly at the time of writing
     *
     * @link https://github.com/vimeo/psalm/issues/3824
     *
     * @var resource|null
     */
    private $socket;

    /** @var bool */
    private $connected = false;
    /** @var ConnectionAddress */
    private $connectionAddress;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        ConnectionAddress $connectionAddress,
        bool $preEmptivelyAttemptToConnect,
        LoggerInterface $logger
    ) {
        $this->connectionAddress = $connectionAddress;
        $this->logger            = $logger;

        $this->socket = socket_create(
            $this->connectionAddress->isTcpAddress() ? AF_INET : AF_UNIX,
            SOCK_STREAM,
            0
        );

        if (! $preEmptivelyAttemptToConnect) {
            return;
        }

        // Pre-emptive attempt to connect, strictly speaking `__construct` should not have side-effects, so if this
        // fails then swallow it. The `Agent` goes on to call connect() anyway, and handles launching of the core agent.
        try {
            $this->connect();
        } catch (FailedToConnect $failedToConnect) {
        }
    }

    /**
     * @psalm-param callable():T $functionToRun
     *
     * @return mixed
     * @psalm-return T
     *
     * @psalm-template T
     */
    private function convertErrorsToExceptions(callable $functionToRun)
    {
        // phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.IncorrectReturnTypeHint
        set_error_handler(
            static function (int $severity, string $message, string $file = '', int $line = 0, array $context = []): bool {
                throw new ErrorException($message, 0, $severity, $file, $line);
            },
            E_STRICT | E_NOTICE | E_WARNING
        );
        // phpcs:enable

        try {
            $returnValue = $functionToRun();
        } finally {
            restore_error_handler();
        }

        return $returnValue;
    }

    public function connect(): void
    {
        if ($this->connected()) {
            return;
        }

        try {
            $this->socket = $this->convertErrorsToExceptions(function () {
                return socket_create(
                    $this->connectionAddress->isTcpAddress() ? AF_INET : AF_UNIX,
                    SOCK_STREAM,
                    0
                ) ?: null;
            });

            Assert::notNull($this->socket, 'Socket was null even after socket_create');

            socket_clear_error($this->socket);

            $this->connected = $this->convertErrorsToExceptions(function () {
                Assert::notNull($this->socket);

                if ($this->connectionAddress->isTcpAddress()) {
                    return socket_connect(
                        $this->socket,
                        $this->connectionAddress->tcpBindAddress(),
                        $this->connectionAddress->tcpBindPort()
                    );
                }

                return socket_connect($this->socket, $this->connectionAddress->socketPath());
            });

            register_shutdown_function([&$this, 'shutdown']);
        } catch (Throwable $e) {
            $this->connected = false;

            throw FailedToConnect::fromSocketPathAndPrevious($this->connectionAddress, $e);
        }
    }

    /** @psalm-assert-if-true !null $this->socket */
    public function connected(): bool
    {
        return $this->socket !== null && $this->connected;
    }

    public function sendCommand(Command $message): string
    {
        if (! $this->connected()) {
            throw NotConnected::fromSocketPath($this->connectionAddress);
        }

        $serializedJsonString = json_encode($message);

        if (! $serializedJsonString) {
            throw Exception\FailedToSendCommand::unableToSerializeCommand($message);
        }

        $size = strlen($serializedJsonString);

        // Socket error is a global state, so we must reset to a known state first...
        socket_clear_error($this->socket);

        if (@socket_send($this->socket, pack('N', $size), 4, 0) === false) {
            throw Exception\FailedToSendCommand::writingMessageSizeToSocket($message, $this->socket, $this->connectionAddress);
        }

        if (@socket_send($this->socket, $serializedJsonString, $size, 0) === false) {
            throw Exception\FailedToSendCommand::writingMessageContentToSocket($message, $this->socket, $this->connectionAddress);
        }

        // Read the response back and drop it. Needed for socket liveness
        $responseLengthPacked = @socket_read($this->socket, 4);

        if ($responseLengthPacked === false) {
            throw Exception\FailedToSendCommand::readingResponseSizeFromSocket($message, $this->socket, $this->connectionAddress);
        }

        if ($responseLengthPacked === '') {
            throw Exception\FailedToSendCommand::fromEmptyResponseSize($message, $this->connectionAddress);
        }

        $responseLengthUnpacked = unpack('Nlen', $responseLengthPacked);

        if (! is_array($responseLengthUnpacked) || ! array_key_exists('len', $responseLengthUnpacked)) {
            throw Exception\FailedToSendCommand::fromFailedResponseUnpack($message, $this->connectionAddress);
        }

        $responseLength = (int) $responseLengthUnpacked['len'];

        if ($responseLength > self::MAXIMUM_RESPONSE_LENGTH_TO_READ) {
            throw Exception\FailedToSendCommand::fromTooLargeResponseLength(
                $responseLength,
                self::MAXIMUM_RESPONSE_LENGTH_TO_READ,
                $message,
                $this->connectionAddress
            );
        }

        $dataRead  = '';
        $bytesRead = 0;
        do {
            $readBuffer = @socket_read($this->socket, $responseLength - $bytesRead);

            if ($readBuffer === false) {
                throw Exception\FailedToSendCommand::readingResponseContentFromSocket(
                    $message,
                    $responseLength,
                    $responseLength - $bytesRead,
                    $bytesRead,
                    $this->socket,
                    $this->connectionAddress
                );
            }

            $dataRead  .= $readBuffer;
            $bytesRead += strlen($readBuffer);
        } while ($bytesRead < $responseLength && $readBuffer !== '');

        $actualResponseLength = strlen($dataRead);
        if ($actualResponseLength < $responseLength) {
            $this->logger->debug(sprintf('Response read from core agent was %d bytes, but we expected %d bytes.', $actualResponseLength, $responseLength));
        }

        return $dataRead;
    }

    public function shutdown(): void
    {
        if (! $this->connected()) {
            return;
        }

        socket_shutdown($this->socket, 2);
        socket_close($this->socket);
    }
}
