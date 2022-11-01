<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Connector\Exception;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Scoutapm\Config;
use Scoutapm\Connector\Command;
use Scoutapm\Connector\ConnectionAddress;
use Scoutapm\Connector\Exception\FailedToSendCommand;

use function socket_create;

use const AF_INET;
use const SOCK_STREAM;

/** @covers \Scoutapm\Connector\Exception\FailedToSendCommand */
final class FailedToSendCommandTest extends TestCase
{
    private const CONNECTION_ADDRESS = 'tcp://127.0.0.01:1234';

    /** @var Command&MockObject */
    private $command;
    /** @var ConnectionAddress */
    private $connectionAddress;
    /** @var resource */
    private $socketResource;

    public function setUp(): void
    {
        $this->command = $this->createMock(Command::class);

        $config = new Config();
        $config->set(Config\ConfigKey::CORE_AGENT_SOCKET_PATH, self::CONNECTION_ADDRESS);
        $this->connectionAddress = ConnectionAddress::fromConfig($config);

        $socketResource = socket_create(AF_INET, SOCK_STREAM, 0);
        self::assertNotFalse($socketResource);
        $this->socketResource = $socketResource;
    }

    public function testWritingMessageSizeToSocket(): void
    {
        $exception = FailedToSendCommand::writingMessageSizeToSocket($this->command, $this->socketResource, $this->connectionAddress);

        self::assertStringContainsString('Failed to write message size', $exception->getMessage());
        self::assertSame(LogLevel::ERROR, $exception->logLevel());
    }

    public function testWritingMessageContentToSocket(): void
    {
        $exception = FailedToSendCommand::writingMessageContentToSocket($this->command, $this->socketResource, $this->connectionAddress);

        self::assertStringContainsString('Failed to write message content', $exception->getMessage());
        self::assertSame(LogLevel::ERROR, $exception->logLevel());
    }

    public function testReadingResponseSizeFromSocket(): void
    {
        $exception = FailedToSendCommand::readingResponseSizeFromSocket($this->command, $this->socketResource, $this->connectionAddress);

        self::assertStringContainsString('Failed to read response size', $exception->getMessage());
        self::assertSame(LogLevel::ERROR, $exception->logLevel());
    }

    public function testFromEmptyResponseSize(): void
    {
        $exception = FailedToSendCommand::fromEmptyResponseSize($this->command, $this->connectionAddress);

        self::assertStringContainsString('Response size was not returned', $exception->getMessage());
        self::assertSame(LogLevel::ERROR, $exception->logLevel());
    }

    public function testFromFailedResponseUnpack(): void
    {
        $exception = FailedToSendCommand::fromFailedResponseUnpack($this->command, $this->connectionAddress);

        self::assertStringContainsString('Response length could not be unpacked', $exception->getMessage());
        self::assertSame(LogLevel::ERROR, $exception->logLevel());
    }

    public function testFromTooLargeResponseLength(): void
    {
        $exception = FailedToSendCommand::fromTooLargeResponseLength(2000, 1000, $this->command, $this->connectionAddress);

        self::assertStringContainsString('Response length returned (2000) exceeded our limit for reading (1000)', $exception->getMessage());
        self::assertSame(LogLevel::NOTICE, $exception->logLevel());
    }

    public function testReadingResponseContentFromSocket(): void
    {
        $exception = FailedToSendCommand::readingResponseContentFromSocket($this->command, 1000, 200, 600, $this->socketResource, $this->connectionAddress);

        self::assertStringContainsString('Failed to read response content', $exception->getMessage());
        self::assertStringContainsString('read 600 of 1000 bytes, tried to read 200 bytes more', $exception->getMessage());
        self::assertSame(LogLevel::ERROR, $exception->logLevel());
    }
}
