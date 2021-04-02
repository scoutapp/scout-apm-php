<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Laravel\Middleware;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Scoutapm\Events\Span\Span;
use Scoutapm\Events\Span\SpanReference;
use Scoutapm\Laravel\Middleware\ActionInstrument;
use Scoutapm\Laravel\Router\AutomaticallyDetermineControllerName;
use Scoutapm\Logger\FilteredLogLevelDecorator;
use Scoutapm\ScoutApmAgent;
use Throwable;

use function uniqid;

/** @covers \Scoutapm\Laravel\Middleware\ActionInstrument */
final class ActionInstrumentTest extends TestCase
{
    /** @var ScoutApmAgent&MockObject */
    private $agent;
    /** @var LoggerInterface&MockObject */
    private $logger;
    /** @var Span&MockObject */
    private $span;
    /** @var AutomaticallyDetermineControllerName&MockObject */
    private $determineControllerName;
    /** @var ActionInstrument */
    private $middleware;

    public function setUp(): void
    {
        parent::setUp();

        $this->agent                   = $this->createMock(ScoutApmAgent::class);
        $this->logger                  = $this->createMock(LoggerInterface::class);
        $this->span                    = $this->createMock(Span::class);
        $this->determineControllerName = $this->createMock(AutomaticallyDetermineControllerName::class);

        $this->middleware = new ActionInstrument(
            $this->agent,
            new FilteredLogLevelDecorator($this->logger, LogLevel::DEBUG),
            $this->determineControllerName
        );
    }

    /** @throws Throwable */
    public function testHandleRecordsControllerName(): void
    {
        $expectedResponse = new Response();

        $controllerName = uniqid('controllerName', true);

        $this->determineControllerName
            ->method('__invoke')
            ->willReturn($controllerName);

        $this->span->expects(self::once())
            ->method('updateName')
            ->with($controllerName);

        $this->logger->expects(self::once())
            ->method('log')
            ->with(LogLevel::DEBUG, '[Scout] Handle ActionInstrument');

        $this->agent
            ->expects(self::once())
            ->method('webTransaction')
            ->with('unknown', self::isType(IsType::TYPE_CALLABLE))
            ->willReturnCallback(
                /** @return mixed */
                function (string $originalName, callable $transaction) {
                    return $transaction(SpanReference::fromSpan($this->span));
                }
            );

        self::assertSame(
            $expectedResponse,
            $this->middleware->handle(
                new Request(),
                static function () use ($expectedResponse) {
                    return $expectedResponse;
                }
            )
        );
    }

    /** @throws Throwable */
    public function testHandleRecordsDoesNotUpdateNameWhenSpanIsNull(): void
    {
        $expectedResponse = new Response();

        $this->span->expects(self::never())
            ->method('updateName');

        $this->agent
            ->expects(self::once())
            ->method('webTransaction')
            ->with('unknown', self::isType(IsType::TYPE_CALLABLE))
            ->willReturnCallback(
                /** @return mixed */
                static function (string $originalName, callable $transaction) {
                    return $transaction(null);
                }
            );

        self::assertSame(
            $expectedResponse,
            $this->middleware->handle(
                new Request(),
                static function () use ($expectedResponse) {
                    return $expectedResponse;
                }
            )
        );
    }

    /** @throws Throwable */
    public function testTagAsErrorIfControllerRaises(): void
    {
        $this->agent->expects(self::once())
            ->method('tagRequest')
            ->with('error', 'true');

        $this->agent
            ->expects(self::once())
            ->method('webTransaction')
            ->with('unknown', self::isType(IsType::TYPE_CALLABLE))
            ->willReturnCallback(
                /** @return mixed */
                function (string $originalName, callable $transaction) {
                    return $transaction(SpanReference::fromSpan($this->span));
                }
            );

        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('Any old exception');

        $this->middleware->handle(
            new Request(),
            static function (): void {
                throw new Exception('Any old exception');
            }
        );
    }
}
