<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Connector;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config;
use Scoutapm\Connector\ConnectionAddress;
use Scoutapm\Connector\Exception\FailedToConnect;
use Scoutapm\Connector\SocketConnector;

/** @covers \Scoutapm\Connector\SocketConnector */
final class SocketConnectorTest extends TestCase
{
    public function testExceptionIsRaisedWhenConnectingToNonExistentSocket() : void
    {
        $config = new Config();
        $config->set(Config\ConfigKey::CORE_AGENT_SOCKET_PATH, '/path/does/not/exist');

        $connector = new SocketConnector(ConnectionAddress::fromConfig($config), false);

        $this->expectException(FailedToConnect::class);
        $this->expectExceptionMessage('socket_connect(): unable to connect [2]: No such file or directory');
        $connector->connect();
    }
}
