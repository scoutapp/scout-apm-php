<?php

declare(strict_types=1);

namespace Scoutapm\Laravel\Providers;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application as LaravelApplication;
use Illuminate\Contracts\Http\Kernel as HttpKernelInterface;
use Illuminate\Contracts\View\Engine;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Kernel as HttpKernelImplementation;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Engines\EngineResolver;
use Laravel\Lumen\Application as LumenApplication;
use Scoutapm\Agent;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;
use Scoutapm\Helper\ComposerPackagesCheck;
use Scoutapm\Laravel\Database\QueryListener;
use Scoutapm\Laravel\Middleware\ActionInstrument;
use Scoutapm\Laravel\Middleware\IgnoredEndpoints;
use Scoutapm\Laravel\Middleware\MiddlewareInstrument;
use Scoutapm\Laravel\Middleware\SendRequestToScout;
use Scoutapm\Laravel\Queue\JobQueueListener;
use Scoutapm\Laravel\Router\AutomaticallyDetermineControllerName;
use Scoutapm\Laravel\Router\DetermineLaravelControllerName;
use Scoutapm\Laravel\Router\DetermineLumenControllerName;
use Scoutapm\Laravel\View\Engine\ScoutViewEngineDecorator;
use Scoutapm\Logger\FilteredLogLevelDecorator;
use Scoutapm\ScoutApmAgent;
use Throwable;

use function array_combine;
use function array_filter;
use function array_map;
use function array_merge;
use function config_path;
use function sprintf;

final class ScoutApmServiceProvider extends ServiceProvider
{
    private const CONFIG_SERVICE_KEY = ScoutApmAgent::class . '_config';
    private const CACHE_SERVICE_KEY  = ScoutApmAgent::class . '_cache';

    private const VIEW_ENGINES_TO_WRAP = ['file', 'php', 'blade'];

    public const INSTRUMENT_LARAVEL_QUEUES = 'laravel_queues';

    /** @var bool */
    private $resolveViewEngineResolverOnBoot = false;

    /** @throws BindingResolutionException */
    public function register(): void
    {
        $this->app->singleton(self::CONFIG_SERVICE_KEY, function () {
            $configRepo = $this->app->make(ConfigRepository::class);

            return Config::fromArray(array_merge(
                array_filter(array_combine(
                    ConfigKey::allConfigurationKeys(),
                    array_map(
                        /** @return mixed */
                        static function (string $configurationKey) use ($configRepo) {
                            return $configRepo->get('scout_apm.' . $configurationKey);
                        },
                        ConfigKey::allConfigurationKeys()
                    )
                )),
                [
                    ConfigKey::FRAMEWORK => 'Laravel',
                    ConfigKey::FRAMEWORK_VERSION => $this->app->version(),
                ]
            ));
        });

        $this->app->singleton(self::CACHE_SERVICE_KEY, static function (Container $app) {
            try {
                return $app->make(CacheManager::class)->store();
            } catch (Throwable $anything) {
                return null;
            }
        });

        $this->app->singleton(FilteredLogLevelDecorator::class, static function (Container $app) {
            return new FilteredLogLevelDecorator(
                $app->make('log'),
                $app->make(self::CONFIG_SERVICE_KEY)->get(ConfigKey::LOG_LEVEL)
            );
        });

        $this->app->singleton(ScoutApmAgent::class, static function (Container $app) {
            return Agent::fromConfig(
                $app->make(self::CONFIG_SERVICE_KEY),
                $app->make(FilteredLogLevelDecorator::class),
                $app->make(self::CACHE_SERVICE_KEY)
            );
        });

        $this->app->singleton(AutomaticallyDetermineControllerName::class, function (Container $app) {
            if ($this->isLumen($app)) {
                return $app->make(DetermineLumenControllerName::class);
            }

            return $app->make(DetermineLaravelControllerName::class);
        });

        if (! $this->app->resolved('view.engine.resolver')) {
            $this->app->afterResolving('view.engine.resolver', function (EngineResolver $engineResolver): void {
                $this->registerWrappedEngines($engineResolver);
            });
        } else {
            $this->resolveViewEngineResolverOnBoot = true;
        }
    }

    public function registerWrappedEngines(EngineResolver $engineResolver): void
    {
        foreach (self::VIEW_ENGINES_TO_WRAP as $engineName) {
            $realEngine = $engineResolver->resolve($engineName);

            $engineResolver->register($engineName, function () use ($realEngine) {
                return $this->wrapEngine($realEngine);
            });
        }
    }

    public function wrapEngine(Engine $realEngine): Engine
    {
        $viewFactory = $this->app->make('view');

        /** @noinspection UnusedFunctionResultInspection */
        $viewFactory->composer('*', static function (View $view) use ($viewFactory): void {
            $viewFactory->share(ScoutViewEngineDecorator::VIEW_FACTORY_SHARED_KEY, $view->name());
        });

        return new ScoutViewEngineDecorator(
            $realEngine,
            $this->app->make(ScoutApmAgent::class),
            $viewFactory
        );
    }

    /** @psalm-assert-if-true LumenApplication $container */
    private function isLumen(Container $container): bool
    {
        return $container instanceof LumenApplication;
    }

    /** @psalm-assert-if-true LaravelApplication $container */
    private function isLaravel(Container $container): bool
    {
        return $container instanceof LaravelApplication;
    }

    /** @throws BindingResolutionException */
    public function boot(
        Container $application,
        ScoutApmAgent $agent,
        FilteredLogLevelDecorator $log
    ): void {
        $log->debug(sprintf('%s Scout Agent is starting', $this->isLumen($application) ? 'Lumen' : 'Laravel'));

        if ($this->isLaravel($application)) {
            ComposerPackagesCheck::logIfLaravelPackageNotPresent($log);

            $this->publishes([
                __DIR__ . '/../config/scout_apm.php' => config_path('scout_apm.php'),
            ]);
        }

        $runningInConsole = false;
        if ($this->isLumen($application) || $this->isLaravel($application)) {
            $runningInConsole = $application->runningInConsole();
        }

        try {
            $connection = $application->make('db')->connection();
            $this->instrumentDatabaseQueries($agent, $connection);
        } catch (Throwable $exception) {
            $log->info(
                sprintf(
                    'Could not set up DB instrumentation: %s',
                    $exception->getMessage()
                ),
                ['exception' => $exception]
            );
        }

        if ($agent->shouldInstrument(self::INSTRUMENT_LARAVEL_QUEUES)) {
            $this->instrumentQueues($agent, $application->make('events'), $runningInConsole);
        }

        if ($this->resolveViewEngineResolverOnBoot) {
            $engineResolver = $this->app->make('view.engine.resolver');
            $this->registerWrappedEngines($engineResolver);

            $this->resolveViewEngineResolverOnBoot = false;
        }

        if ($runningInConsole || ! $application->has(HttpKernelInterface::class)) {
            return;
        }

        $httpKernel = $application->make(HttpKernelInterface::class);
        /** @psalm-suppress ArgumentTypeCoercion */
        $this->instrumentMiddleware($httpKernel);
    }

    /**
     * @param HttpKernelImplementation $kernel
     *
     * @noinspection PhpDocSignatureInspection
     */
    private function instrumentMiddleware(HttpKernelInterface $kernel): void
    {
        $kernel->prependMiddleware(MiddlewareInstrument::class);
        $kernel->pushMiddleware(ActionInstrument::class);

        // Must be outside any other scout instruments. When this middleware's terminate is called, it will complete
        // the request, and send it to the CoreAgent.
        $kernel->prependMiddleware(IgnoredEndpoints::class);
        $kernel->prependMiddleware(SendRequestToScout::class);
    }

    private function instrumentDatabaseQueries(ScoutApmAgent $agent, Connection $connection): void
    {
        $connection->listen(static function (QueryExecuted $query) use ($agent): void {
            (new QueryListener($agent))->__invoke($query);
        });
    }

    private function instrumentQueues(ScoutApmAgent $agent, Dispatcher $eventDispatcher, bool $runningInConsole): void
    {
        $listener = new JobQueueListener($agent);

        $eventDispatcher->listen(JobProcessing::class, static function (JobProcessing $event) use ($listener, $runningInConsole): void {
            if ($runningInConsole) {
                $listener->startNewRequestForJob();
            }

            $listener->startSpanForJob($event);
        });

        $eventDispatcher->listen(JobProcessed::class, static function (JobProcessed $event) use ($listener, $runningInConsole): void {
            $listener->stopSpanForJob();

            if (! $runningInConsole) {
                return;
            }

            $listener->sendRequestForJob();
        });
    }
}
