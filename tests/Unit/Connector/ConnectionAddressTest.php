<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Connector;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config;
use Scoutapm\Connector\ConnectionAddress;
use Throwable;

/** @covers \Scoutapm\Connector\ConnectionAddress */
final class ConnectionAddressTest extends TestCase
{
    private function connectionAddressFromString(string $connectionAddress) : ConnectionAddress
    {
        $config = new Config();
        $config->set(Config\ConfigKey::CORE_AGENT_SOCKET_PATH, $connectionAddress);

        return ConnectionAddress::fromConfig($config);
    }

    private function expectExceptionInCall(callable $callableThatShouldThrow, string $message) : void
    {
        try {
            $callableThatShouldThrow();
            self::fail($message);
        } catch (Throwable $_) {
            // exception is expected
        }
    }

    public function testConnectionAddressWithTcpAddress() : void
    {
        $connectionAddress = $this->connectionAddressFromString('tcp://192.168.1.250:1234');

        self::assertSame('tcp://192.168.1.250:1234', $connectionAddress->toString());

        self::assertFalse($connectionAddress->isSocketPath());
        $this->expectExceptionInCall(
            static function () use ($connectionAddress) : void {
                $connectionAddress->socketPath();
            },
            'Trying to retrieve a socket path should throw an exception'
        );

        self::assertTrue($connectionAddress->isTcpAddress());

        self::assertSame('192.168.1.250:1234', $connectionAddress->tcpBindAddressPort());
        self::assertSame('192.168.1.250', $connectionAddress->tcpBindAddress());
        self::assertSame(1234, $connectionAddress->tcpBindPort());
    }

    public function testConnectionAddressWithSocketPath() : void
    {
        $connectionAddress = $this->connectionAddressFromString('/path/to/my/socket');

        self::assertSame('/path/to/my/socket', $connectionAddress->toString());

        self::assertTrue($connectionAddress->isSocketPath());
        self::assertSame('/path/to/my/socket', $connectionAddress->socketPath());

        self::assertFalse($connectionAddress->isTcpAddress());
        $this->expectExceptionInCall(
            static function () use ($connectionAddress) : void {
                $connectionAddress->tcpBindAddressPort();
            },
            'Trying to retrieve a TCP address/port should throw an exception'
        );
        $this->expectExceptionInCall(
            static function () use ($connectionAddress) : void {
                $connectionAddress->tcpBindAddress();
            },
            'Trying to retrieve a TCP address should throw an exception'
        );
        $this->expectExceptionInCall(
            static function () use ($connectionAddress) : void {
                $connectionAddress->tcpBindPort();
            },
            'Trying to retrieve a TCP port should throw an exception'
        );
    }
}
