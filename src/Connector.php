<?php

namespace Scoutapm;

use Scoutapm\Events\Request;

class Connector
{
    private $config;

    private $socket;

    public function __construct(\Scoutapm\Config $config)
    {
        $this->config = $config;
        $this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        socket_connect($this->socket, $this->config->get('socket_location'));
        register_shutdown_function([&$this, 'shutdown']);
    }

    public function sendRequest(Request $request) : bool
    {
        $registerMessage = json_encode([
            'Register' => [
            'app' => $this->config->get('app_name'),
            'key' => $this->config->get('key'),
            'api_version' => $this->config->get('api_version'),
            ]
        ]);
        $registerSize = strlen($registerMessage);
        socket_send($this->socket, pack('N', $registerSize), 4, 0);
        socket_send($this->socket, $registerMessage, $registerSize, 0);
        $registerResponseLength = socket_read($this->socket, 4);
        socket_read($this->socket, unpack('N', $registerResponseLength)[1]);


        // Send Request
        $request = json_encode(new RequestSerializer($request));
        
        $requestSize = strlen($request);
        socket_send($this->socket, pack('N', $requestSize), 4, 0);
        socket_send($this->socket, $request, $requestSize, 0);

        // Read Response
        $responseLength = socket_read($this->socket, 4);
        socket_read($this->socket, @unpack('N', $responseLength)[1]);

        return socket_last_error($this->socket) === 0;

    }

    public function shutdown()
    {
        socket_shutdown($this->socket, 2);
        socket_close($this->socket);
    }
}
