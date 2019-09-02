<?php

declare(strict_types=1);

namespace Scoutapm\Connector;

use Scoutapm\Connector\Exception\FailedToConnect;
use Scoutapm\Connector\Exception\NotConnected;
use Throwable;
use const AF_UNIX;
use const SOCK_STREAM;
use function json_encode;
use function pack;
use function register_shutdown_function;
use function socket_clear_error;
use function socket_close;
use function socket_connect;
use function socket_create;
use function socket_read;
use function socket_send;
use function socket_shutdown;
use function strlen;
use function unpack;

/** @internal */
final class SocketConnector implements Connector
{
    /** @var resource */
    private $socket;

    /** @var bool */
    private $connected = false;

    /** @var string */
    private $socketPath;

    public function __construct(string $socketPath)
    {
        $this->socketPath = $socketPath;

        $this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);

        // Pre-emptive attempt to connect, strictly speaking `__construct` should not have side-effects, so if this
        // fails then swallow it. The `Agent` goes on to call connect() anyway, and handles launching of the core agent.
        try {
            $this->connect();
        } catch (FailedToConnect $failedToConnect) {
        }
    }

    public function connect() : void
    {
        if ($this->connected()) {
            return;
        }

        try {
            socket_clear_error($this->socket);
            $this->connected = socket_connect($this->socket, $this->socketPath);
            register_shutdown_function([&$this, 'shutdown']);
        } catch (Throwable $e) {
            $this->connected = false;
            throw FailedToConnect::fromSocketPathAndPrevious($this->socketPath, $e);
        }
    }

    public function connected() : bool
    {
        return $this->connected;
    }

    public function sendCommand(Command $message) : bool
    {
        if (! $this->connected()) {
            throw NotConnected::fromSocketPath($this->socketPath);
        }

        $serializedJsonString = json_encode($message);

        $size = strlen($serializedJsonString);

        // Socket error is a global state, so we must reset to a known state first...
        socket_clear_error($this->socket);

        if (socket_send($this->socket, pack('N', $size), 4, 0) === false) {
            throw Exception\FailedToSendCommand::writingMessageSizeToSocket($message, $this->socket, $this->socketPath);
        }

        if (socket_send($this->socket, $serializedJsonString, $size, 0) === false) {
            throw Exception\FailedToSendCommand::writingMessageContentToSocket($message, $this->socket, $this->socketPath);
        }

        // Read the response back and drop it. Needed for socket liveness
        $responseLength = socket_read($this->socket, 4);

        if ($responseLength === false) {
            throw Exception\FailedToSendCommand::readingResponseSizeFromSocket($message, $this->socket, $this->socketPath);
        }

        if (socket_read($this->socket, unpack('N', $responseLength)[1]) === false) {
            throw Exception\FailedToSendCommand::readingResponseContentFromSocket($message, $this->socket, $this->socketPath);
        }

        return true;
    }

    public function shutdown() : void
    {
        if (! $this->connected()) {
            return;
        }

        socket_shutdown($this->socket, 2);
        socket_close($this->socket);
    }
}
