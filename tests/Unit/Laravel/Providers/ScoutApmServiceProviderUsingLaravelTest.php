<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Laravel\Providers;

use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Http\Kernel as HttpKernelInterface;
use Illuminate\Contracts\View\Engine;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Kernel as HttpKernelImplementation;
use Illuminate\Routing\Router;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory as ViewFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Scoutapm\Logger\FilteredLogLevelDecorator;

use function class_exists;
use function mkdir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

/** @covers \Scoutapm\Laravel\Providers\ScoutApmServiceProvider */
final class ScoutApmServiceProviderUsingLaravelTest extends ScoutApmServiceProviderTestBase
{
    /**
     * Helper to create a Laravel application instance that has very basic wiring up of services that our Laravel
     * binding library actually interacts with in some way.
     *
     * @psalm-return Application&MockObject
     */
    protected function createFrameworkApplicationFulfillingBasicRequirementsForScout(bool $runningInConsole = false): Container
    {
        if (! class_exists(Application::class)) {
            self::markTestSkipped(Application::class . ' is not available in the current dependency tree');
        }

        $application = $this->getMockBuilder(Application::class)
            ->setMethods(['runningInConsole'])
            ->getMock();

        $application
            ->method('runningInConsole')
            ->willReturn($runningInConsole);

        $application->singleton(
            LoggerInterface::class,
            function (): LoggerInterface {
                return $this->createMock(LoggerInterface::class);
            }
        );
        $application->alias(LoggerInterface::class, 'log');

        $application->singleton(
            FilteredLogLevelDecorator::class,
            static function () use ($application): FilteredLogLevelDecorator {
                return new FilteredLogLevelDecorator(
                    $application->make(LoggerInterface::class),
                    LogLevel::DEBUG
                );
            }
        );

        $storagePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('scoutapm-test-laravel-storage', true) . DIRECTORY_SEPARATOR;
        mkdir($storagePath);
        $application->useStoragePath($storagePath);

        $application->singleton(
            HttpKernelInterface::class,
            function () use ($application): HttpKernelInterface {
                return new HttpKernelImplementation($application, $this->createMock(Router::class));
            }
        );

        $application->singleton(
            'view',
            function (): ViewFactory {
                return $this->createMock(ViewFactory::class);
            }
        );

        $application->singleton(
            'view.engine.resolver',
            function (): EngineResolver {
                $viewEngineResolver = new EngineResolver();

                foreach (self::VIEW_ENGINES_TO_WRAP as $viewEngineName) {
                    $viewEngineResolver->register(
                        $viewEngineName,
                        function () use ($viewEngineName): Engine {
                            return new class ($viewEngineName) implements Engine {
                                /** @var string */
                                private $viewEngineName;

                                public function __construct(string $viewEngineName)
                                {
                                    $this->viewEngineName = $viewEngineName;
                                }

                                /** @inheritDoc */
                                public function get($path, array $data = []): string
                                {
                                    return sprintf(
                                        'Fake view engine for [%s] - rendered path "%s"',
                                        $this->viewEngineName,
                                        $path
                                    );
                                }
                            };
                        }
                    );
                }

                return $viewEngineResolver;
            }
        );

        $application->singleton(
            'cache',
            static function () use ($application): CacheManager {
                return new CacheManager($application);
            }
        );

        // Older versions of Laravel used `path.config` service name for path...
        $application->singleton(
            'path.config',
            static function (): string {
                return sys_get_temp_dir();
            }
        );

        $application->singleton(
            'config',
            static function (): ConfigRepository {
                return new ConfigRepository();
            }
        );

        return $application;
    }
}
