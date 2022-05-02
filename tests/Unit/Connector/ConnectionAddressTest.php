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
    private function connectionAddressFromString(string $connectionAddress): ConnectionAddress
    {
        $config = new Config();
        $config->set(Config\ConfigKey::CORE_AGENT_SOCKET_PATH, $connectionAddress);

        return ConnectionAddress::fromConfig($config);
    }

    private function expectExceptionInCall(callable $callableThatShouldThrow, string $message): void
    {
        try {
            $callableThatShouldThrow();
            self::fail($message);
        } catch (Throwable $expected) {
            // exception is expected
        }
    }

    /**
     * @return string[][]|int[][]
     * @psalm-return list<array{configuration:string,address:string,port:int,both:string}>
     */
    public function tcpAddressProvider(): array
    {
        return [
            [
                'configuration' => 'tcp://192.168.1.250:1234',
                'address' => '192.168.1.250',
                'port' => 1234,
                'both' => '192.168.1.250:1234',
            ],
            [
                'configuration' => 'tcp://my-hostname:1234',
                'address' => 'my-hostname',
                'port' => 1234,
                'both' => 'my-hostname:1234',
            ],
            [
                'configuration' => 'tcp://192.168.1.250',
                'address' => '192.168.1.250',
                'port' => 6590,
                'both' => '192.168.1.250:6590',
            ],
            [
                'configuration' => 'tcp://my-hostname',
                'address' => 'my-hostname',
                'port' => 6590,
                'both' => 'my-hostname:6590',
            ],
            [
                'configuration' => 'tcp://192.168.1.250:',
                'address' => '192.168.1.250',
                'port' => 6590,
                'both' => '192.168.1.250:6590',
            ],
            [
                'configuration' => 'tcp://my-hostname:',
                'address' => 'my-hostname',
                'port' => 6590,
                'both' => 'my-hostname:6590',
            ],
            [
                'configuration' => 'tcp://:1234',
                'address' => '127.0.0.1',
                'port' => 1234,
                'both' => '127.0.0.1:1234',
            ],
            [
                'configuration' => 'tcp://:',
                'address' => '127.0.0.1',
                'port' => 6590,
                'both' => '127.0.0.1:6590',
            ],
            [
                'configuration' => 'tcp://',
                'address' => '127.0.0.1',
                'port' => 6590,
                'both' => '127.0.0.1:6590',
            ],
        ];
    }

    /** @dataProvider tcpAddressProvider */
    public function testConnectionAddressWithTcpAddress(
        string $configuration,
        string $address,
        int $port,
        string $both
    ): void {
        $connectionAddress = $this->connectionAddressFromString($configuration);

        self::assertSame($configuration, $connectionAddress->toString());

        self::assertFalse($connectionAddress->isSocketPath());
        $this->expectExceptionInCall(
            static function () use ($connectionAddress): void {
                $connectionAddress->socketPath();
            },
            'Trying to retrieve a socket path should throw an exception'
        );

        self::assertTrue($connectionAddress->isTcpAddress());

        self::assertSame($address, $connectionAddress->tcpBindAddress());
        self::assertSame($port, $connectionAddress->tcpBindPort());
        self::assertSame($both, $connectionAddress->tcpBindAddressPort());
    }

    public function testConnectionAddressWithSocketPath(): void
    {
        $connectionAddress = $this->connectionAddressFromString('/path/to/my/socket');

        self::assertSame('/path/to/my/socket', $connectionAddress->toString());

        self::assertTrue($connectionAddress->isSocketPath());
        self::assertSame('/path/to/my/socket', $connectionAddress->socketPath());

        self::assertFalse($connectionAddress->isTcpAddress());
        $this->expectExceptionInCall(
            static function () use ($connectionAddress): void {
                $connectionAddress->tcpBindAddressPort();
            },
            'Trying to retrieve a TCP address/port should throw an exception'
        );
        $this->expectExceptionInCall(
            static function () use ($connectionAddress): void {
                $connectionAddress->tcpBindAddress();
            },
            'Trying to retrieve a TCP address should throw an exception'
        );
        $this->expectExceptionInCall(
            static function () use ($connectionAddress): void {
                $connectionAddress->tcpBindPort();
            },
            'Trying to retrieve a TCP port should throw an exception'
        );
    }
}
