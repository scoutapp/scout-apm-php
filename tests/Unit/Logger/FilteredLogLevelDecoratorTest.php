<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Logger;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Scoutapm\Logger\FilteredLogLevelDecorator;

use function str_repeat;
use function uniqid;

/** @covers \Scoutapm\Logger\FilteredLogLevelDecorator */
final class FilteredLogLevelDecoratorTest extends TestCase
{
    private const PREPEND_SCOUT_TAG = '[Scout] ';

    /** @var LoggerInterface&MockObject */
    private $decoratedLogger;

    public function setUp(): void
    {
        parent::setUp();

        $this->decoratedLogger = $this->createMock(LoggerInterface::class);
    }

    public function testInvalidLogLevelStringGivesClearErrorMessage(): void
    {
        $this->decoratedLogger
            ->expects(self::once())
            ->method('log')
            ->with(
                LogLevel::ERROR,
                'Log level foo was not a valid PSR-3 compatible log level. '
                . 'Should be one of: debug, info, notice, warning, error, critical, alert, emergency',
                self::anything()
            );

        new FilteredLogLevelDecorator($this->decoratedLogger, 'foo');
    }

    public function testLogMessagesHaveScoutTagPrepended(): void
    {
        $decorator = new FilteredLogLevelDecorator($this->decoratedLogger, LogLevel::DEBUG);

        $logMessage = uniqid('logMessage', true);

        $this->decoratedLogger
            ->expects(self::once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                self::PREPEND_SCOUT_TAG . $logMessage,
                []
            );

        $decorator->debug($logMessage);
    }

    public function testLogMessagesBelowThresholdAreNotLogged(): void
    {
        $decorator = new FilteredLogLevelDecorator($this->decoratedLogger, LogLevel::NOTICE);

        $this->decoratedLogger
            ->expects(self::never())
            ->method('log');

        $decorator->info(uniqid('logMessage', true));
    }

    public function testLogMessagesAboveThresholdAreLogged(): void
    {
        $decorator = new FilteredLogLevelDecorator($this->decoratedLogger, LogLevel::NOTICE);

        $logMessage = uniqid('logMessage', true);
        $context    = [uniqid('foo', true) => uniqid('bar', true)];

        $this->decoratedLogger
            ->expects(self::once())
            ->method('log')
            ->with(
                LogLevel::WARNING,
                self::stringContains($logMessage),
                $context
            );

        $decorator->warning($logMessage, $context);
    }

    /** @return array<int, array<int, string>> */
    public function invalidLogLevelProvider(): array
    {
        return [
            ['lizard'],
            [''],
            [uniqid('randomString', true)],
            [str_repeat('a', 1024)],
        ];
    }

    /** @dataProvider invalidLogLevelProvider */
    public function testInvalidLogLevelsAreLoggedAsErrors(string $invalidLogLevel): void
    {
        $this->decoratedLogger
            ->expects(self::once())
            ->method('log')
            ->with(
                LogLevel::ERROR,
                self::stringContains($invalidLogLevel . ' was not a valid PSR-3'),
                self::anything()
            );

        new FilteredLogLevelDecorator($this->decoratedLogger, $invalidLogLevel);
    }

    /** @dataProvider invalidLogLevelProvider */
    public function testInvalidLogLevelsDefaultToDebug(string $invalidLogLevel): void
    {
        $decorator = new FilteredLogLevelDecorator($this->decoratedLogger, $invalidLogLevel);

        $message = 'info message';

        $this->decoratedLogger
            ->expects(self::once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                self::stringContains($message),
                self::anything()
            );

        $decorator->debug($message);
    }
}
