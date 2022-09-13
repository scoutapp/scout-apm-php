<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Laravel\Providers;

use Closure;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Http\Kernel as HttpKernelInterface;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Foundation\Http\Kernel as HttpKernelImplementation;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\View\Engines\EngineResolver;
use Laravel\Lumen\Application as LumenApplication;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionProperty;
use Scoutapm\Agent;
use Scoutapm\Config;
use Scoutapm\Connector\Command;
use Scoutapm\Connector\Connector;
use Scoutapm\Events\Metadata;
use Scoutapm\Extension\PotentiallyAvailableExtensionCapabilities;
use Scoutapm\Laravel\Middleware\ActionInstrument;
use Scoutapm\Laravel\Middleware\IgnoredEndpoints;
use Scoutapm\Laravel\Middleware\MiddlewareInstrument;
use Scoutapm\Laravel\Middleware\SendRequestToScout;
use Scoutapm\Laravel\Providers\ScoutApmServiceProvider;
use Scoutapm\Laravel\View\Engine\ScoutViewEngineDecorator;
use Scoutapm\Logger\FilteredLogLevelDecorator;
use Scoutapm\ScoutApmAgent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function assert;
use function json_decode;
use function json_encode;
use function putenv;
use function uniqid;

abstract class ScoutApmServiceProviderTestBase extends TestCase
{
    private const CACHE_SERVICE_KEY = ScoutApmAgent::class . '_cache';

    protected const VIEW_ENGINES_TO_WRAP = ['file', 'php', 'blade'];

    /**
     * @var LumenApplication|LaravelApplication
     * @psalm-var LumenApplication|LaravelApplication&MockObject
     */
    private $application;

    /** @var ScoutApmServiceProvider */
    private $serviceProvider;

    /** @var Connection&MockObject */
    private $connection;

    public function setUp(): void
    {
        parent::setUp();

        $this->application = $this->createFrameworkApplicationFulfillingBasicRequirementsForScout();
        $this->connection  = $this->createMock(Connection::class);

        /** @psalm-suppress PossiblyInvalidArgument */
        $this->serviceProvider = new ScoutApmServiceProvider($this->application);
    }

    /** @throws BindingResolutionException */
    public function testScoutAgentIsRegistered(): void
    {
        self::assertFalse($this->application->has(ScoutApmAgent::class));

        $this->serviceProvider->register();

        self::assertTrue($this->application->has(ScoutApmAgent::class));

        $agent = $this->application->make(ScoutApmAgent::class);

        self::assertInstanceOf(ScoutApmAgent::class, $agent);
    }

    /**
     * @throws BindingResolutionException
     * @throws ReflectionException
     */
    public function testScoutAgentUsesLaravelCacheWhenConfigured(): void
    {
        $this->application->singleton('config', static function () {
            return new ConfigRepository([
                'cache' => [
                    'default' => 'array',
                    'stores' => [
                        'array' => ['driver' => 'array'],
                    ],
                ],
            ]);
        });

        $this->serviceProvider->register();
        $agent = $this->application->make(ScoutApmAgent::class);

        $cacheProperty = new ReflectionProperty($agent, 'cache');
        $cacheProperty->setAccessible(true);
        $cacheUsed = $cacheProperty->getValue($agent);

        self::assertInstanceOf(CacheRepository::class, $cacheUsed);
        self::assertInstanceOf(ArrayStore::class, $cacheUsed->getStore());
    }

    /**
     * @throws BindingResolutionException
     * @throws ReflectionException
     */
    public function testScoutAgentPullsConfigFromConfigRepositoryAndEnv(): void
    {
        $configName = uniqid('configName', true);
        $configKey  = uniqid('configKey', true);

        putenv('SCOUT_KEY=' . $configKey);

        $this->application->singleton('config', static function () use ($configName) {
            return new ConfigRepository([
                'scout_apm' => [Config\ConfigKey::APPLICATION_NAME => $configName],
            ]);
        });

        $this->serviceProvider->register();

        $agent = $this->application->make(ScoutApmAgent::class);

        $configProperty = new ReflectionProperty($agent, 'config');
        $configProperty->setAccessible(true);

        $configUsed = $configProperty->getValue($agent);
        assert($configUsed instanceof Config);

        self::assertSame($configName, $configUsed->get(Config\ConfigKey::APPLICATION_NAME));
        self::assertSame($configKey, $configUsed->get(Config\ConfigKey::APPLICATION_KEY));

        putenv('SCOUT_KEY');
    }

    /** @throws Throwable */
    public function testViewEngineResolversHaveBeenWrapped(): void
    {
        $this->serviceProvider->register();
        $this->bootServiceProvider();

        $templateName = uniqid('test_template_name', true);

        $viewResolver = $this->application->make('view.engine.resolver');
        assert($viewResolver instanceof EngineResolver);

        $viewFactory = $this->application->make('view');
        assert($viewFactory instanceof MockObject);
        $viewFactory->expects(self::once())
            ->method('composer')
            ->with('*', self::isType(IsType::TYPE_CALLABLE))
            ->willReturnCallback(function (string $whichViews, callable $composer) use ($templateName): void {
                $mockView = $this->createMock(View::class);
                assert($mockView instanceof View && $mockView instanceof MockObject);
                $mockView->expects(self::once())
                    ->method('name')
                    ->willReturn($templateName);
                $composer($mockView);
            });

        $viewFactory->expects(self::once())
            ->method('share')
            ->with(ScoutViewEngineDecorator::VIEW_FACTORY_SHARED_KEY, $templateName);

        $viewFactory->expects(self::once())
            ->method('shared')
            ->with(ScoutViewEngineDecorator::VIEW_FACTORY_SHARED_KEY, 'unknown')
            ->willReturn($templateName);

        (new PotentiallyAvailableExtensionCapabilities())->clearRecordedCalls();
        $engine = $viewResolver->resolve('file');
        self::assertSame('Fake view engine for [file] - rendered path "/path/to/view"', $engine->get('/path/to/view'));

        $agent = $this->application->make(ScoutApmAgent::class);
        assert($agent instanceof Agent);

        /** @psalm-suppress DeprecatedMethod */
        $requestMade = $agent->getRequest();
        assert($requestMade !== null);
        $commands = $requestMade->jsonSerialize()['BatchCommand']['commands'];

        self::assertCount(4, $commands);

        self::assertArrayHasKey(1, $commands);
        self::assertArrayHasKey('StartSpan', $commands[1]);
        self::assertArrayHasKey('operation', $commands[1]['StartSpan']);
        self::assertSame('View/' . $templateName, $commands[1]['StartSpan']['operation']);
    }

    /** @throws Throwable */
    public function testMiddlewareAreRegisteredOnBootForHttpRequest(): void
    {
        if (! $this->application instanceof LaravelApplication) {
            self::markTestSkipped('Middleware are only injected for Laravel applications');
        }

        $kernel = $this->application->make(HttpKernelInterface::class);
        assert($kernel instanceof HttpKernelImplementation);

        $this->serviceProvider->register();

        self::assertFalse($kernel->hasMiddleware(MiddlewareInstrument::class));
        self::assertFalse($kernel->hasMiddleware(ActionInstrument::class));
        self::assertFalse($kernel->hasMiddleware(IgnoredEndpoints::class));
        self::assertFalse($kernel->hasMiddleware(SendRequestToScout::class));

        $this->bootServiceProvider();

        self::assertTrue($kernel->hasMiddleware(MiddlewareInstrument::class));
        self::assertTrue($kernel->hasMiddleware(ActionInstrument::class));
        self::assertTrue($kernel->hasMiddleware(IgnoredEndpoints::class));
        self::assertTrue($kernel->hasMiddleware(SendRequestToScout::class));
    }

    /** @throws Throwable */
    public function testMiddlewareAreNotRegisteredOnBootForConsoleRequest(): void
    {
        if (! $this->application instanceof LaravelApplication) {
            self::markTestSkipped('Middleware are only injected for Laravel applications');
        }

        $this->application = $this->createFrameworkApplicationFulfillingBasicRequirementsForScout(true);

        /** @psalm-suppress PossiblyInvalidArgument */
        $this->serviceProvider = new ScoutApmServiceProvider($this->application);

        $kernel = $this->application->make(HttpKernelInterface::class);
        assert($kernel instanceof HttpKernelImplementation);

        $this->serviceProvider->register();

        self::assertFalse($kernel->hasMiddleware(MiddlewareInstrument::class));
        self::assertFalse($kernel->hasMiddleware(ActionInstrument::class));
        self::assertFalse($kernel->hasMiddleware(IgnoredEndpoints::class));
        self::assertFalse($kernel->hasMiddleware(SendRequestToScout::class));

        $this->bootServiceProvider();

        self::assertFalse($kernel->hasMiddleware(MiddlewareInstrument::class));
        self::assertFalse($kernel->hasMiddleware(ActionInstrument::class));
        self::assertFalse($kernel->hasMiddleware(IgnoredEndpoints::class));
        self::assertFalse($kernel->hasMiddleware(SendRequestToScout::class));
    }

    /** @throws Throwable */
    public function testDatabaseQueryListenerIsRegistered(): void
    {
        $this->serviceProvider->register();

        $this->connection->expects(self::once())
            ->method('listen')
            ->with(self::isInstanceOf(Closure::class));

        $dbManager = $this->createMock(DatabaseManager::class);
        $dbManager->expects(self::once())
            ->method('connection')
            ->willReturn($this->connection);

        $this->application->singleton('db', static function () use ($dbManager) {
            return $dbManager;
        });

        $this->bootServiceProvider();
    }

    /** @throws Throwable */
    public function testJobQueueIsInstrumentedWhenRunningInConsole(): void
    {
        $this->application = $this->createFrameworkApplicationFulfillingBasicRequirementsForScout(true);

        /** @psalm-suppress PossiblyInvalidArgument */
        $this->serviceProvider = new ScoutApmServiceProvider($this->application);
        $this->serviceProvider->register();

        $this->application->singleton(
            ScoutApmAgent::class,
            function (): ScoutApmAgent {
                return $this->createMock(ScoutApmAgent::class);
            }
        );

        $agent = $this->application->make(ScoutApmAgent::class);
        assert($agent instanceof Agent && $agent instanceof MockObject);

        $agent
            ->expects(self::exactly(2))
            ->method('shouldInstrument')
            ->withConsecutive(
                [ScoutApmServiceProvider::INSTRUMENT_LARAVEL_QUEUES],
                [ScoutApmServiceProvider::INSTRUMENT_LARAVEL_CONSOLE]
            )
            ->willReturnOnConsecutiveCalls(
                true,
                false
            );

        $agent->expects(self::once())
            ->method('startNewRequest');

        $agent->expects(self::once())
            ->method('startSpan');

        $agent->expects(self::once())
            ->method('stopSpan');

        $agent->expects(self::once())
            ->method('connect');

        $agent->expects(self::once())
            ->method('send');

        $this->bootServiceProvider();

        $events = $this->application->make('events');
        assert($events instanceof Dispatcher);

        $jobWithName = $this->createMock(Job::class);
        $jobWithName->method('resolveName')->willReturn('JobName');

        $events->dispatch(new JobProcessing('foo', $jobWithName));
        $events->dispatch(new JobProcessed('foo', $jobWithName));
    }

    /** @throws Throwable */
    public function testJobQueueIsInstrumentedWhenRunningInHttp(): void
    {
        $this->serviceProvider->register();

        $this->application->singleton(
            ScoutApmAgent::class,
            function (): ScoutApmAgent {
                return $this->createMock(ScoutApmAgent::class);
            }
        );

        $agent = $this->application->make(ScoutApmAgent::class);
        assert($agent instanceof Agent && $agent instanceof MockObject);

        $agent->expects(self::once())
            ->method('shouldInstrument')
            ->with(ScoutApmServiceProvider::INSTRUMENT_LARAVEL_QUEUES)
            ->willReturn(true);

        $agent->expects(self::never())
            ->method('startNewRequest');

        $agent->expects(self::once())
            ->method('startSpan');

        $agent->expects(self::once())
            ->method('stopSpan');

        $agent->expects(self::never())
            ->method('connect');

        $agent->expects(self::never())
            ->method('send');

        $this->bootServiceProvider();

        $events = $this->application->make('events');
        assert($events instanceof Dispatcher);

        $jobWithName = $this->createMock(Job::class);
        $jobWithName->method('resolveName')->willReturn('JobName');

        $events->dispatch(new JobProcessing('foo', $jobWithName));
        $events->dispatch(new JobProcessed('foo', $jobWithName));
    }

    /** @throws Throwable */
    public function testJobQueuesAreNotInstrumentedWhenNotConfigured(): void
    {
        $this->application     = $this->createFrameworkApplicationFulfillingBasicRequirementsForScout(true);
        $this->serviceProvider = new ScoutApmServiceProvider($this->application);

        $this->application->singleton('config', static function () {
            return new ConfigRepository([
                'scout_apm' => [
                    Config\ConfigKey::DISABLED_INSTRUMENTS => [ScoutApmServiceProvider::INSTRUMENT_LARAVEL_QUEUES],
                ],
            ]);
        });

        $this->serviceProvider->register();

        $this->application->singleton(
            ScoutApmAgent::class,
            function (): ScoutApmAgent {
                return $this->createMock(ScoutApmAgent::class);
            }
        );

        $agent = $this->application->make(ScoutApmAgent::class);
        assert($agent instanceof Agent && $agent instanceof MockObject);

        $agent
            ->expects(self::exactly(2))
            ->method('shouldInstrument')
            ->withConsecutive(
                [ScoutApmServiceProvider::INSTRUMENT_LARAVEL_QUEUES],
                [ScoutApmServiceProvider::INSTRUMENT_LARAVEL_CONSOLE]
            )
            ->willReturnOnConsecutiveCalls(
                false,
                false
            );

        $agent->expects(self::never())
            ->method('startNewRequest');

        $agent->expects(self::never())
            ->method('startSpan');

        $agent->expects(self::never())
            ->method('stopSpan');

        $agent->expects(self::never())
            ->method('connect');

        $agent->expects(self::never())
            ->method('send');

        $this->bootServiceProvider();

        $events = $this->application->make('events');
        assert($events instanceof Dispatcher);

        $events->dispatch(new JobProcessing('foo', $this->createMock(Job::class)));
        $events->dispatch(new JobProcessed('foo', $this->createMock(Job::class)));
    }

    /** @throws Throwable */
    public function testConsoleCommandIsInstrumentedWhenEnabled(): void
    {
        $this->application = $this->createFrameworkApplicationFulfillingBasicRequirementsForScout(true);

        /** @psalm-suppress PossiblyInvalidArgument */
        $this->serviceProvider = new ScoutApmServiceProvider($this->application);
        $this->serviceProvider->register();

        $this->application->singleton(
            ScoutApmAgent::class,
            function (): ScoutApmAgent {
                return $this->createMock(ScoutApmAgent::class);
            }
        );

        $agent = $this->application->make(ScoutApmAgent::class);
        assert($agent instanceof Agent && $agent instanceof MockObject);

        $agent
            ->expects(self::exactly(2))
            ->method('shouldInstrument')
            ->withConsecutive(
                [ScoutApmServiceProvider::INSTRUMENT_LARAVEL_QUEUES],
                [ScoutApmServiceProvider::INSTRUMENT_LARAVEL_CONSOLE]
            )
            ->willReturnOnConsecutiveCalls(
                false,
                true
            );

        $agent->expects(self::once())
            ->method('startNewRequest');

        $agent->expects(self::once())
            ->method('addContext');

        $agent->expects(self::once())
            ->method('startSpan');

        $agent->expects(self::once())
            ->method('stopSpan');

        $agent->expects(self::once())
            ->method('connect');

        $agent->expects(self::once())
            ->method('send');

        $this->bootServiceProvider();

        $events = $this->application->make('events');
        assert($events instanceof Dispatcher);

        $jobWithName = $this->createMock(Job::class);
        $jobWithName->method('resolveName')->willReturn('JobName');

        $input  = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        $events->dispatch(new CommandStarting('app:sparkle', $input, $output));
        $events->dispatch(new CommandFinished('app:sparkle', $input, $output, 0));
    }

    /** @throws Throwable */
    public function testConsoleCommandIsNotInstrumentedWhenDisabled(): void
    {
        $this->application = $this->createFrameworkApplicationFulfillingBasicRequirementsForScout(true);

        /** @psalm-suppress PossiblyInvalidArgument */
        $this->serviceProvider = new ScoutApmServiceProvider($this->application);
        $this->serviceProvider->register();

        $this->application->singleton(
            ScoutApmAgent::class,
            function (): ScoutApmAgent {
                return $this->createMock(ScoutApmAgent::class);
            }
        );

        $agent = $this->application->make(ScoutApmAgent::class);
        assert($agent instanceof Agent && $agent instanceof MockObject);

        $agent
            ->expects(self::exactly(2))
            ->method('shouldInstrument')
            ->withConsecutive(
                [ScoutApmServiceProvider::INSTRUMENT_LARAVEL_QUEUES],
                [ScoutApmServiceProvider::INSTRUMENT_LARAVEL_CONSOLE]
            )
            ->willReturnOnConsecutiveCalls(
                false,
                false
            );

        $agent->expects(self::never())
            ->method('startNewRequest');

        $agent->expects(self::never())
            ->method('addContext');

        $agent->expects(self::never())
            ->method('startSpan');

        $agent->expects(self::never())
            ->method('stopSpan');

        $agent->expects(self::never())
            ->method('connect');

        $agent->expects(self::never())
            ->method('send');

        $this->bootServiceProvider();

        $events = $this->application->make('events');
        assert($events instanceof Dispatcher);

        $jobWithName = $this->createMock(Job::class);
        $jobWithName->method('resolveName')->willReturn('JobName');

        $input  = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        $events->dispatch(new CommandStarting('app:sparkle', $input, $output));
        $events->dispatch(new CommandFinished('app:sparkle', $input, $output, 0));
    }

    /** @throws BindingResolutionException */
    public function testMetadataContainsFrameworkNameAndVersion(): void
    {
        $connectorMock = $this->createMock(Connector::class);

        $this->application->singleton('config', static function () {
            return new ConfigRepository([
                'scout_apm' => [
                    Config\ConfigKey::APPLICATION_NAME => 'Laravel Provider Test',
                    Config\ConfigKey::APPLICATION_KEY => 'test application key',
                    Config\ConfigKey::MONITORING_ENABLED => true,
                ],
            ]);
        });

        $this->serviceProvider->register();

        $this->application->singleton(ScoutApmAgent::class, function () use ($connectorMock) {
            /** @psalm-suppress InvalidArgument */
            return Agent::fromConfig(
                $this->application->make(Config::class),
                $this->application->make(FilteredLogLevelDecorator::class),
                $this->application->make(self::CACHE_SERVICE_KEY),
                $connectorMock
            );
        });

        $this->bootServiceProvider();

        $connectorMock->expects(self::exactly(3))
            ->method('sendCommand')
            ->withConsecutive(
                [self::isInstanceOf(Command::class)],
                [
                    self::callback(static function (Metadata $metadata) {
                        /** @psalm-var array{framework: string, framework_version: string} $flattenedMetadata */
                        $flattenedMetadata = json_decode(json_encode($metadata), true)['ApplicationEvent']['event_value'];

                        self::assertArrayHasKey('framework', $flattenedMetadata);
                        self::assertSame('Laravel', $flattenedMetadata['framework']);

                        self::assertArrayHasKey('framework_version', $flattenedMetadata);
                        self::assertNotSame('', $flattenedMetadata['framework_version']);

                        return true;
                    }),
                ],
                [self::isInstanceOf(Command::class)]
            );

        $this->application
            ->make(ScoutApmAgent::class)
            ->send();
    }

    /** @throws BindingResolutionException */
    private function bootServiceProvider(): void
    {
        $log = $this->application->make(FilteredLogLevelDecorator::class);
        $this->serviceProvider->boot(
            $this->application,
            $this->application->make(ScoutApmAgent::class),
            $log
        );
    }

    /**
     * Helper to create a Laravel application instance that has very basic wiring up of services that our Laravel
     * binding library actually interacts with in some way.
     *
     * @return LumenApplication|LaravelApplication
     * @psalm-return LumenApplication|LaravelApplication&MockObject
     */
    abstract protected function createFrameworkApplicationFulfillingBasicRequirementsForScout(bool $runningInConsole = false): Container;
}
