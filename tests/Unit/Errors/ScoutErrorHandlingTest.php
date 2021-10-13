<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Errors;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use ReflectionProperty;
use RuntimeException;
use Scoutapm\Config;
use Scoutapm\Errors\ErrorEvent;
use Scoutapm\Errors\ScoutClient\ErrorReportingClient;
use Scoutapm\Errors\ScoutErrorHandling;

use function set_exception_handler;

/** @covers \Scoutapm\Errors\ScoutErrorHandling */
final class ScoutErrorHandlingTest extends TestCase
{
    /** @var ErrorReportingClient&MockObject */
    private $reportingClient;
    /** @var TestLogger */
    private $logger;

    public function setUp(): void
    {
        parent::setUp();

        $this->reportingClient = $this->createMock(ErrorReportingClient::class);
        $this->logger          = new TestLogger();
    }

    private function errorHandlingFromConfig(Config $config): ScoutErrorHandling
    {
        return new ScoutErrorHandling(
            $this->reportingClient,
            $config,
            $this->logger
        );
    }

    public function testExceptionsAreNotSentWhenDisabled(): void
    {
        $handling = $this->errorHandlingFromConfig(Config::fromArray([Config\ConfigKey::ERRORS_ENABLED => false]));

        $this->reportingClient
            ->expects(self::never())
            ->method('sendErrorToScout');

        $handling->handleException(new RuntimeException());
        $handling->sendCollectedErrors();
    }

    public function testExceptionsAreNotSentWhenIgnored(): void
    {
        $handling = $this->errorHandlingFromConfig(Config::fromArray([
            Config\ConfigKey::ERRORS_ENABLED => true,
            Config\ConfigKey::ERRORS_IGNORED_EXCEPTIONS => [RuntimeException::class],
        ]));

        $this->reportingClient
            ->expects(self::never())
            ->method('sendErrorToScout');

        $handling->handleException(new RuntimeException());
        $handling->sendCollectedErrors();
    }

    public function testExceptionsAreSentWhenEnabled(): void
    {
        $handling = $this->errorHandlingFromConfig(Config::fromArray([
            Config\ConfigKey::ERRORS_ENABLED => true,
            Config\ConfigKey::ERRORS_IGNORED_EXCEPTIONS => false,
        ]));

        $this->reportingClient
            ->expects(self::once())
            ->method('sendErrorToScout')
            ->with(self::isInstanceOf(ErrorEvent::class));

        $handling->handleException(new RuntimeException());
        $handling->sendCollectedErrors();
    }

    public function testShutdownDoesNotSendErrorsWhenDisabled(): void
    {
        $handling = $this->errorHandlingFromConfig(Config::fromArray([Config\ConfigKey::ERRORS_ENABLED => false]));

        $this->reportingClient
            ->expects(self::never())
            ->method('sendErrorToScout');

        $handling->handleShutdown();
        $handling->sendCollectedErrors();
    }

    public function testShutdownDoesNotSendErrorWhenErrorTypeIgnored(): void
    {
        /**
         * Errors in PHP 7+ are all exceptions, except things like fatal/parse errors. Using {@see trigger_error} won't
         * work because the {@see error_get_last} would return `null`. Realistically, only way to test is an
         * integration type test.
         */
        self::markTestSkipped('Unable to unit test this scenario');
    }

    public function testShutdownSendsExceptionsWhenEnabled(): void
    {
        /**
         * Errors in PHP 7+ are all exceptions, except things like fatal/parse errors. Using {@see trigger_error} won't
         * work because the {@see error_get_last} would return `null`. Realistically, only way to test is an
         * integration type test.
         */
        self::markTestSkipped('Unable to unit test this scenario');
    }

    public function testListenersCanBeRegistered(): void
    {
        $phpunitHandler = set_exception_handler($previous = static function (): void {
        });

        $handling = $this->errorHandlingFromConfig(Config::fromArray([Config\ConfigKey::ERRORS_ENABLED => true]));
        $handling->registerListeners();

        // Check the previous handler was stored
        $previousHandlerProperty = new ReflectionProperty($handling, 'oldExceptionHandler');
        $previousHandlerProperty->setAccessible(true);
        self::assertSame($previous, $previousHandlerProperty->getValue($handling));

        // Check the new handler was set to Scout's error handler
        $changed = set_exception_handler(static function (): void {
        });
        self::assertSame([$handling, 'handleException'], $changed);

        // Restore original PHPUnit error handler
        set_exception_handler($phpunitHandler);
    }

    public function testListenersAreNotRegisteredWhenDisabled(): void
    {
        $phpunitHandler = set_exception_handler(static function (): void {
        });
        set_exception_handler($phpunitHandler);

        $handling = $this->errorHandlingFromConfig(Config::fromArray([Config\ConfigKey::ERRORS_ENABLED => false]));
        $handling->registerListeners();

        // Check the new handler was set to Scout's error handler
        $handlerToCheck = set_exception_handler(static function (): void {
        });
        self::assertSame($phpunitHandler, $handlerToCheck);

        set_exception_handler($phpunitHandler);
    }
}
