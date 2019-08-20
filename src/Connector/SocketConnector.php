<?php

declare(strict_types=1);

namespace Scoutapm\Connector;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Scoutapm\Events\Metadata;
use Scoutapm\Events\Request\Request;
use Throwable;
use const AF_UNIX;
use const SOCK_STREAM;
use function json_encode;
use function pack;
use function register_shutdown_function;
use function socket_close;
use function socket_connect;
use function socket_create;
use function socket_last_error;
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

    /** @var string */
    private $appName;

    /** @var string */
    private $appKey;

    /** @var string */
    private $apiVersion;

    public function __construct(string $socketPath, string $appName, string $appKey, string $apiVersion)
    {
        $this->socketPath = $socketPath;
        $this->appName    = $appName;
        $this->appKey     = $appKey;
        $this->apiVersion = $apiVersion;

        $this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        $this->connect();
        register_shutdown_function([&$this, 'shutdown']);
    }

    public function connect() : void
    {
        try {
            $this->connected = socket_connect($this->socket, $this->socketPath);
        } catch (Throwable $e) {
            $this->connected = false;
        }
    }

    public function connected() : bool
    {
        return $this->connected;
    }

    /**
     * @param mixed $message
     */
    private function sendMessage($message) : void
    {
        $serializedJsonString = json_encode($message);

        $size = strlen($serializedJsonString);
        socket_send($this->socket, pack('N', $size), 4, 0);
        socket_send($this->socket, $serializedJsonString, $size, 0);

        // Read the response back and drop it. Needed for socket liveness
        $responseLength = socket_read($this->socket, 4);
        socket_read($this->socket, unpack('N', $responseLength)[1]);
    }

    /** @throws Exception */
    public function sendRequest(Request $request) : bool
    {
        $this->sendMessage([
            'Register' => [
                'app' => $this->appName,
                'key' => $this->appKey,
                'language' => 'php',
                'api_version' => $this->apiVersion,
            ],
        ]);

        $this->sendMessage(new Metadata(
            new DateTimeImmutable('now', new DateTimeZone('UTC'))
        ));

        // Send the whole Request as a batch command
        $this->sendMessage([
            'BatchCommand' => ['commands' => $request],
        ]);

        return socket_last_error($this->socket) === 0;
    }

    public function shutdown() : void
    {
        if ($this->connected !== true) {
            return;
        }

        socket_shutdown($this->socket, 2);
        socket_close($this->socket);
    }
}