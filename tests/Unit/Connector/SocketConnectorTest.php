<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Connector;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Scoutapm\Config;
use Scoutapm\Connector\Command;
use Scoutapm\Connector\ConnectionAddress;
use Scoutapm\Connector\Exception\FailedToConnect;
use Scoutapm\Connector\Exception\FailedToSendCommand;
use Scoutapm\Connector\SocketConnector;
use Scoutapm\Helper\Platform;

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
    /** @var LoggerInterface&MockObject */
    private $loggerMock;

    public function setUp(): void
    {
        parent::setUp();

        if (Platform::isWindows()) {
            // Because of the way the tests are run (launching the binary etc) this has not yet been updated to run on
            // other platforms yet.
            self::markTestSkipped('Test only runs on Linux at the moment');
        }

        $this->loggerMock = $this->createMock(LoggerInterface::class);
    }

    public function testExceptionIsRaisedWhenConnectingToNonExistentSocket(): void
    {
        $connector = new SocketConnector(
            ConnectionAddress::fromConfig(Config::fromArray([Config\ConfigKey::CORE_AGENT_SOCKET_PATH => '/path/does/not/exist'])),
            false,
            $this->loggerMock
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
            false,
            $this->loggerMock
        );
        self::assertFalse($connector->connected());
        self::assertFileExists('/proc/' . $this->pidsStarted[0]);
    }

    public function testSocketPreemptivelyConnects(): void
    {
        $this->runTestSocketServerAndReturnPid(10002);

        $connector = new SocketConnector(
            ConnectionAddress::fromConfig(Config::fromArray([Config\ConfigKey::CORE_AGENT_SOCKET_PATH => 'tcp://localhost:10002'])),
            true,
            $this->loggerMock
        );
        self::assertTrue($connector->connected());
    }

    public function testExceptionIsRaisedWhenCommandCannotBeSerialized(): void
    {
        $this->runTestSocketServerAndReturnPid(10003);

        $connector = new SocketConnector(
            ConnectionAddress::fromConfig(Config::fromArray([Config\ConfigKey::CORE_AGENT_SOCKET_PATH => 'tcp://localhost:10003'])),
            true,
            $this->loggerMock
        );
        $connector->connect();

        $this->expectException(FailedToSendCommand::class);
        $this->expectExceptionMessage('Failed to serialize command of type');

        $connector->sendCommand(new class implements Command {
            public function cleanUp(): void
            {
            }

            // phpcs:disable SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName
            /** @return mixed */
            #[\ReturnTypeWillChange] // Not really, this is just necessary to keep compatibility with PHP 8.0 and below
            public function jsonSerialize()
            {
                return "\xB1\x31"; // should cause json_encode to return false
            }

            // phpcs:enable
        });
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
