<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Laravel\Providers;

use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\Engine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory as ViewFactory;
use Laravel\Lumen\Application;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use ReflectionClass;
use ReflectionProperty;
use Scoutapm\Logger\FilteredLogLevelDecorator;

use function class_exists;
use function method_exists;
use function mkdir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

/** @covers \Scoutapm\Laravel\Providers\ScoutApmServiceProvider */
final class ScoutApmServiceProviderUsingLumenTest extends ScoutApmServiceProviderTestBase
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

        $applicationMockBuilder = $this->getMockBuilder(Application::class)
            ->onlyMethods(['runningInConsole']);

        if (! (new ReflectionClass(Application::class))->hasMethod('terminating')) {
            $applicationMockBuilder->addMethods(['terminating']);
        }

        $application = $applicationMockBuilder->getMock();

        $application
            ->method('runningInConsole')
            ->willReturn($runningInConsole);

        /**
         * This property write is needed otherwise Lumen overwrites any configuration already done if the key is not
         * set, thus breaking our expectations.
         *
         * @see \Laravel\Lumen\Application::configure()
         */
        $loadedConfigurationsProperty = new ReflectionProperty($application, 'loadedConfigurations');
        $loadedConfigurationsProperty->setAccessible(true);
        $loadedConfigurations          = $loadedConfigurationsProperty->getValue($application);
        $loadedConfigurations['cache'] = true;
        $loadedConfigurationsProperty->setValue($application, $loadedConfigurations);

        $application->instance('app', $application);
        $application->alias(
            \Illuminate\Contracts\Foundation\Application::class,
            'app'
        );

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

        if (method_exists($application, 'useStoragePath')) {
            $storagePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('scoutapm-test-lumen-storage', true) . DIRECTORY_SEPARATOR;
            mkdir($storagePath);
            $application->useStoragePath($storagePath);
        }

        $application->make('view');
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
            CacheManager::class,
            static function () use ($application): CacheManager {
                /** @noinspection PhpParamsInspection */
                /** @psalm-suppress InvalidArgument */

                return new CacheManager($application);
            }
        );
        $application->alias('cache', CacheManager::class);

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

        /**
         * For some reason, if we don't `make` these services first, the tests fail in Lumen 5.5.*. Which is
         * very odd, since in those cases, we're overwriting it immediately after. Without lengthy investigation into
         * the depths of Lumen, I don't know the exact reason for this, but it may just be a bug solved in later
         * versions, or some unclear magic.
         */
        $application->make('db');
        $application->make('config');

        return $application;
    }
}
