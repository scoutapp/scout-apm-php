<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Middleware;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Scoutapm\Middleware\ScoutApmMiddleware;
use Scoutapm\ScoutApmAgent;

/** @covers \Scoutapm\Middleware\ScoutApmMiddleware */
final class ScoutApmMiddlewareTest extends TestCase
{
    /** @var ScoutApmAgent&MockObject */
    private $scoutApmAgent;
    /** @var LoggerInterface&MockObject */
    private $logger;
    /** @var ServerRequestInterface&MockObject */
    private $request;
    /** @var RequestHandlerInterface&MockObject */
    private $handler;
    /** @var ScoutApmMiddleware */
    private $middleware;

    public function setUp(): void
    {
        parent::setUp();

        $this->scoutApmAgent = $this->createMock(ScoutApmAgent::class);
        $this->logger        = $this->createMock(LoggerInterface::class);
        $this->request       = $this->createMock(ServerRequestInterface::class);
        $this->handler       = $this->createMock(RequestHandlerInterface::class);

        $this->middleware = new ScoutApmMiddleware($this->scoutApmAgent, $this->logger);
    }

    private function mockWebTransactionForRequestPath(): void
    {
        $uriPath = '/server/request/path';
        $uri     = $this->createMock(UriInterface::class);
        $uri->expects(self::once())->method('getPath')->willReturn($uriPath);
        $this->request->expects(self::once())->method('getUri')->willReturn($uri);

        $this->scoutApmAgent
            ->method('webTransaction')
            ->willReturnCallback(
            /** @param callable():ResponseInterface $callable */
                static function (string $path, callable $callable) use ($uriPath): ResponseInterface {
                    self::assertSame($uriPath, $path);

                    return $callable();
                }
            );
    }

    public function testProcessRecordsWebTransactionAndSendsAndReturnsResponse(): void
    {
        $this->mockWebTransactionForRequestPath();

        $this->scoutApmAgent
            ->expects(self::once())
            ->method('connect');

        $this->scoutApmAgent
            ->expects(self::once())
            ->method('send');

        $this->scoutApmAgent
            ->expects(self::never())
            ->method('tagRequest');

        $response = $this->createMock(ResponseInterface::class);

        $this->handler
            ->expects(self::once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($response);

        self::assertSame($response, $this->middleware->process($this->request, $this->handler));
    }

    public function testHandlerThrowingExceptionCausesRequestToBeTaggedAndSent(): void
    {
        $this->mockWebTransactionForRequestPath();

        $this->scoutApmAgent
            ->expects(self::once())
            ->method('connect');

        $this->scoutApmAgent
            ->expects(self::once())
            ->method('send');

        $this->scoutApmAgent
            ->expects(self::once())
            ->method('tagRequest')
            ->with('error', 'true');

        $exception = new RuntimeException('oh no');

        $this->scoutApmAgent
            ->expects(self::once())
            ->method('recordThrowable')
            ->with($exception);

        $this->handler
            ->expects(self::once())
            ->method('handle')
            ->with($this->request)
            ->willThrowException($exception);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('oh no');
        $this->middleware->process($this->request, $this->handler);
    }

    public function testFailureToSendLogsMessageToLoggerAndReturnsResponse(): void
    {
        $this->mockWebTransactionForRequestPath();

        $this->scoutApmAgent
            ->expects(self::once())
            ->method('connect');

        $this->scoutApmAgent
            ->expects(self::once())
            ->method('send')
            ->willThrowException(new RuntimeException('a bad exception'));

        $this->scoutApmAgent
            ->expects(self::never())
            ->method('tagRequest');

        $this->logger
            ->expects(self::once())
            ->method('debug')
            ->with('PSR-15 Send to Scout failed: a bad exception');

        $response = $this->createMock(ResponseInterface::class);

        $this->handler
            ->expects(self::once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($response);

        self::assertSame($response, $this->middleware->process($this->request, $this->handler));
    }
}
