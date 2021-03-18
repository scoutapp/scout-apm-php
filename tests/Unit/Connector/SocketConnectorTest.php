<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Connector;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config;
use Scoutapm\Connector\ConnectionAddress;
use Scoutapm\Connector\Exception\FailedToConnect;
use Scoutapm\Connector\SocketConnector;

use function exec;
use function file_exists;
use function realpath;
use function usleep;

/** @covers \Scoutapm\Connector\SocketConnector */
final class SocketConnectorTest extends TestCase
{
    private const TEST_SOCKET_SERVER_EXECUTABLE = __DIR__ . '/../../test-socket-server/test-socket-server';

    /** @var int[] */
    private $pidsStarted = [];

    public function testExceptionIsRaisedWhenConnectingToNonExistentSocket(): void
    {
        $connector = new SocketConnector(
            ConnectionAddress::fromConfig(Config::fromArray([Config\ConfigKey::CORE_AGENT_SOCKET_PATH => '/path/does/not/exist'])),
            false
        );

        $this->expectException(FailedToConnect::class);
        $this->expectExceptionMessage('socket_connect(): unable to connect [2]: No such file or directory');
        $connector->connect();
    }

    public function testSocketDoesNotPreemptivelyConnect(): void
    {
        $this->runTestSocketServerAndReturnPid(10001);

        $connector = new SocketConnector(
            ConnectionAddress::fromConfig(Config::fromArray([Config\ConfigKey::CORE_AGENT_SOCKET_PATH => 'tcp://localhost:10001'])),
            false
        );
        self::assertFalse($connector->connected());
        self::assertFileExists('/proc/' . $this->pidsStarted[0]);
    }

    public function testSocketPreemptivelyConnects(): void
    {
        $this->runTestSocketServerAndReturnPid(10002);

        $connector = new SocketConnector(
            ConnectionAddress::fromConfig(Config::fromArray([Config\ConfigKey::CORE_AGENT_SOCKET_PATH => 'tcp://localhost:10002'])),
            true
        );
        self::assertTrue($connector->connected());
    }

    private function runTestSocketServerAndReturnPid(int $port): void
    {
        self::assertGreaterThanOrEqual(1024, $port);

        $realpath = realpath(self::TEST_SOCKET_SERVER_EXECUTABLE);

        self::assertNotFalse($realpath);

        $this->pidsStarted[] = (int) exec($realpath . ' ' . $port . ' > /dev/null 2>&1 & echo $!');

        // Yuck, but gives the process a chance to start
        usleep(500000);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->pidsStarted as $pid) {
            if (! file_exists('/proc/' . $pid)) {
                continue;
            }

            exec('kill -1 ' . $pid . ' > /dev/null 2>&1');
        }

        $this->pidsStarted = [];
    }
}
