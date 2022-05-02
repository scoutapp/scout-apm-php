<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Laravel\Router;

use Exception;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Router;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Scoutapm\Laravel\Router\DetermineLumenControllerName;
use Scoutapm\Logger\FilteredLogLevelDecorator;

use function class_exists;

/**
 * @covers \Scoutapm\Laravel\Router\DetermineLumenControllerName
 * @psalm-import-type LumenRouterActionShape from DetermineLumenControllerName
 */
final class DetermineLumenControllerNameTest extends TestCase
{
    private const EXPECTED_CONTROLLER_PREFIX = 'Controller/';

    /** @var LoggerInterface&MockObject */
    private $logger;
    /** @var Router&MockObject */
    private $router;
    /** @var DetermineLumenControllerName */
    private $determineControllerName;

    public function setUp(): void
    {
        parent::setUp();

        if (! class_exists(Router::class)) {
            self::markTestSkipped(Router::class . ' is not available in the current dependency tree');
        }

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->router = $this->createMock(Router::class);

        $this->determineControllerName = new DetermineLumenControllerName(
            new FilteredLogLevelDecorator($this->logger, LogLevel::DEBUG),
            $this->router
        );
    }

    /**
     * @psalm-return array<
     *      string,
     *      array{
     *          0: LumenRouterActionShape,
     *          1: string,
     *      }
     * >
     */
    public function validRouteActionConfigurationToControllerNameProvider(): array
    {
        return [
            'usesWithAs' => [
                ['uses' => 'A@b', 'as' => 'myroute'],
                'myroute',
            ],
            'usesWithoutAs' => [
                ['uses' => 'A@b'],
                'A@b',
            ],
            'callableWithAs' => [
                [
                    'as' => 'myroute',
                    static function (): void {
                    },
                ],
                'myroute',
            ],
            'callableWithoutAs' => [
                [
                    static function (): void {
                    },
                ],
                'closure_%s@%d',
            ],
        ];
    }

    /**
     * @psalm-param LumenRouterActionShape $actionConfiguration
     *
     * @dataProvider validRouteActionConfigurationToControllerNameProvider
     */
    public function testControllerNameIsReturnedWhenRouteMatches(array $actionConfiguration, string $expectedControllerSuffix): void
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/theurl', 'REQUEST_METHOD' => 'GET']);

        $this->router
            ->expects(self::once())
            ->method('getRoutes')
            ->willReturn([
                'GET/theurl' => [
                    'method' => 'GET',
                    'uri' => '/theurl',
                    'action' => $actionConfiguration,
                ],
            ]);

        self::assertStringMatchesFormat(
            self::EXPECTED_CONTROLLER_PREFIX . $expectedControllerSuffix,
            $this->determineControllerName->__invoke($request)
        );
    }

    public function testControllerNameIsUnknownWhenMatchedRouteDoesNotFollowExpectedFormat(): void
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/theurl', 'REQUEST_METHOD' => 'GET']);

        $this->router
            ->expects(self::once())
            ->method('getRoutes')
            ->willReturn([
                'GET/theurl' => [
                    'method' => 'GET',
                    'uri' => '/theurl',
                    'action' => [],
                ],
            ]);

        self::assertSame(
            self::EXPECTED_CONTROLLER_PREFIX . 'unknown',
            $this->determineControllerName->__invoke($request)
        );
    }

    public function testControllerNameIsUnknownAndExceptionLoggedWhenRouterThrowsException(): void
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/theurl', 'REQUEST_METHOD' => 'GET']);

        $this->router
            ->expects(self::once())
            ->method('getRoutes')
            ->willThrowException(new Exception('oh no'));

        $this->logger->expects(self::once())
            ->method('log')
            ->with(
                LogLevel::DEBUG,
                '[Scout] Exception obtaining name of Lumen endpoint: oh no'
            );

        self::assertSame(
            self::EXPECTED_CONTROLLER_PREFIX . 'unknown',
            $this->determineControllerName->__invoke($request)
        );
    }
}
