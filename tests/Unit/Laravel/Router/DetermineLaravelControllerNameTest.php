<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Laravel\Router;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Scoutapm\Laravel\Router\DetermineLaravelControllerName;
use Scoutapm\Logger\FilteredLogLevelDecorator;

use function class_exists;
use function uniqid;

/** @covers \Scoutapm\Laravel\Router\DetermineLaravelControllerName */
final class DetermineLaravelControllerNameTest extends TestCase
{
    private const EXPECTED_CONTROLLER_PREFIX = 'Controller/';

    /** @var LoggerInterface&MockObject */
    private $logger;
    /** @var Router&MockObject */
    private $router;
    /** @var DetermineLaravelControllerName */
    private $determineControllerName;

    public function setUp(): void
    {
        parent::setUp();

        if (! class_exists(Router::class)) {
            self::markTestSkipped(Router::class . ' is not available in the current dependency tree');
        }

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->router = $this->createMock(Router::class);

        $this->determineControllerName = new DetermineLaravelControllerName(
            new FilteredLogLevelDecorator($this->logger, LogLevel::DEBUG),
            $this->router
        );
    }

    public function testControllerNameIsReturnedWhenRouteHasControllerKey(): void
    {
        $controllerName = uniqid('controllerName', true);

        $this->router->expects(self::once())
            ->method('current')
            ->willReturn(new Route('GET', '/default-url', ['controller' => $controllerName]));

        self::assertSame(
            self::EXPECTED_CONTROLLER_PREFIX . $controllerName,
            $this->determineControllerName->__invoke(new Request())
        );
    }

    public function testUrlIsReturnedWhenRouteHasControllerKey(): void
    {
        $url = uniqid('url', true);

        $this->router->expects(self::once())
            ->method('current')
            ->willReturn(new Route('GET', $url, []));

        self::assertSame(
            self::EXPECTED_CONTROLLER_PREFIX . $url,
            $this->determineControllerName->__invoke(new Request())
        );
    }

    public function testUnknownIsReturnedWhenNoRouteFound(): void
    {
        $this->router->expects(self::once())
            ->method('current')
            ->willReturn(null);

        self::assertSame(
            self::EXPECTED_CONTROLLER_PREFIX . 'unknown',
            $this->determineControllerName->__invoke(new Request())
        );
    }

    public function testUnknownIsReturnedAndExceptionLoggedWhenRouterThrowsException(): void
    {
        $this->router->expects(self::once())
            ->method('current')
            ->willThrowException(new Exception('oh no'));

        $this->logger->expects(self::once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                '[Scout] Exception obtaining name of Laravel endpoint: oh no'
            );

        self::assertSame(
            self::EXPECTED_CONTROLLER_PREFIX . 'unknown',
            $this->determineControllerName->__invoke(new Request())
        );
    }
}
