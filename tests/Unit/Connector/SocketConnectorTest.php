<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Connector;

use PHPUnit\Framework\TestCase;
use Scoutapm\Connector\Exception\FailedToConnect;
use Scoutapm\Connector\SocketConnector;

/** @covers \Scoutapm\Connector\SocketConnector */
final class SocketConnectorTest extends TestCase
{
    public function testExceptionIsRaisedWhenConnectingToNonExistentSocket() : void
    {
        $connector = new SocketConnector('/path/does/not/exist', false);

        $this->expectException(FailedToConnect::class);
        $this->expectExceptionMessage('socket_connect(): unable to connect [2]: No such file or directory');
        $connector->connect();
    }
}
