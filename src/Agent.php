<?php

declare(strict_types=1);

namespace Scoutapm;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Scoutapm\Cache\DevNullCache;
use Scoutapm\Config\ConfigKey;
use Scoutapm\Config\IgnoredEndpoints;
use Scoutapm\Connector\ConnectionAddress;
use Scoutapm\Connector\Connector;
use Scoutapm\Connector\Exception\FailedToConnect;
use Scoutapm\Connector\Exception\FailedToSendCommand;
use Scoutapm\Connector\Exception\NotConnected;
use Scoutapm\Connector\SocketConnector;
use Scoutapm\CoreAgent\AutomaticDownloadAndLaunchManager;
use Scoutapm\CoreAgent\Downloader;
use Scoutapm\CoreAgent\Launcher;
use Scoutapm\CoreAgent\Verifier;
use Scoutapm\Errors\ErrorHandling;
use Scoutapm\Errors\ErrorHandlingDiscoveryFactory;
use Scoutapm\Events\Metadata;
use Scoutapm\Events\RegisterMessage;
use Scoutapm\Events\Request\Exception\SpanLimitReached;
use Scoutapm\Events\Request\Request;
use Scoutapm\Events\Span\Span;
use Scoutapm\Events\Span\SpanReference;
use Scoutapm\Events\Tag\Tag;
use Scoutapm\Extension\DoNotInvokeAnyExtensionCapabilities;
use Scoutapm\Extension\ExtensionCapabilities;
use Scoutapm\Extension\PotentiallyAvailableExtensionCapabilities;
use Scoutapm\Extension\Version;
use Scoutapm\Helper\ComposerPackagesCheck;
use Scoutapm\Helper\DetermineHostname\DetermineHostnameWithConfigOverride;
use Scoutapm\Helper\FindApplicationRoot\FindApplicationRootWithConfigOverride;
use Scoutapm\Helper\FindRequestHeaders\FindRequestHeaders;
use Scoutapm\Helper\FindRequestHeaders\FindRequestHeadersUsingServerGlobal;
use Scoutapm\Helper\LocateFileOrFolder\LocateFileOrFolder;
use Scoutapm\Helper\LocateFileOrFolder\LocateFileOrFolderUsingFilesystem;
use Scoutapm\Helper\RootPackageGitSha\FindRootPackageGitShaWithHerokuAndConfigOverride;
use Scoutapm\Helper\Superglobals\Superglobals;
use Scoutapm\Helper\Superglobals\SuperglobalsArrays;
use Scoutapm\Logger\FilteredLogLevelDecorator;
use Scoutapm\MongoDB\QueryTimeCollector;
use Throwable;

use function array_filter;
use function array_values;
use function count;
use function extension_loaded;
use function in_array;
use function is_array;
use function is_string;
use function json_encode;
use function sprintf;
use function stripos;

final class Agent implements ScoutApmAgent
{
    private const CACHE_KEY_METADATA_SENT = 'scout_metadata_sent';

    private const METADATA_CACHE_TTL_SECONDS = 600;

    private const WARN_WHEN_EXTENSION_IS_OLDER_THAN = '1.6.0';

    /** @var Config */
    private $config;
    /** @var Request|null */
    private $request;
    /** @var Connector */
    private $connector;
    /** @var FilteredLogLevelDecorator */
    private $logger;
    /** @var IgnoredEndpoints Class that helps check incoming http paths vs. the configured ignore list*/
    private $ignoredEndpoints;
    /** @var ?list<string> */
    private $ignoredJobs;
    /** @var bool If this request was marked as ignored*/
    private $isIgnored = false;
    /** @var ?string */
    private $ignoreReason;
    /** @var ExtensionCapabilities */
    private $phpExtension;
    /** @var CacheInterface */
    private $cache;
    /** @var bool */
    private $registered = false;
    /** @var bool */
    private $spanLimitReached = false;
    /** @var LocateFileOrFolder */
    private $locateFileOrFolder;
    /** @var ErrorHandling */
    private $errorHandling;
    /** @var Superglobals */
    private $superglobals;
    /** @var FindRequestHeaders */
    private $findRequestHeaders;

    private function __construct(
        Config $configuration,
        Connector $connector,
        FilteredLogLevelDecorator $logger,
        ExtensionCapabilities $phpExtension,
        CacheInterface $cache,
        LocateFileOrFolder $locateFileOrFolder,
        ErrorHandling $errorHandling,
        Superglobals $superglobals,
        FindRequestHeadersUsingServerGlobal $findRequestHeaders
    ) {
        $this->config             = $configuration;
        $this->connector          = $connector;
        $this->logger             = $logger;
        $this->phpExtension       = $phpExtension;
        $this->cache              = $cache;
        $this->locateFileOrFolder = $locateFileOrFolder;
        $this->errorHandling      = $errorHandling;
        $this->superglobals       = $superglobals;
        $this->findRequestHeaders = $findRequestHeaders;

        if ($this->config->get(ConfigKey::MONITORING_ENABLED)) {
            $this->warnIfConfigValueIsNotSet(ConfigKey::APPLICATION_NAME);
            $this->warnIfConfigValueIsNotSet(ConfigKey::APPLICATION_KEY);

            if (extension_loaded('mongodb')) {
                QueryTimeCollector::register($this);
            }
        }

        $this->startNewRequest();

        $this->ignoredEndpoints = new IgnoredEndpoints($configuration->get(ConfigKey::IGNORED_ENDPOINTS));

        $this->logger->debug('Configuration: ' . json_encode($this->config->asArrayWithSecretsRemoved()));
    }

    private function warnIfConfigValueIsNotSet(string $configKey): void
    {
        $configValue = $this->config->get($configKey);

        if ($configValue !== null && (! is_string($configValue) || $configValue !== '')) {
            return;
        }

        $this->logger->warning(sprintf('Config key "%s" should be set, but it was empty', $configKey));
    }

    private static function createConnectorFromConfig(Config $config, LoggerInterface $logger): SocketConnector
    {
        return new SocketConnector(
            ConnectionAddress::fromConfig($config),
            $config->get(ConfigKey::MONITORING_ENABLED),
            $logger
        );
    }

    /**
     * The {@see PotentiallyAvailableExtensionCapabilities} constructor implementation automatically invokes the
     * {@see \scoutapm_enable_instrumentation()} function, which enables the extension, if it is available. However, if
     * the extension is present, but monitoring is disabled, the extension is still enabled. Therefore, when setting up
     * the Agent, if monitoring is NOT enabled, then use the new alternate implementation in
     * {@see DoNotInvokeAnyExtensionCapabilities} which is essentially a no-op. This means the extension will remain
     * dormant if monitoring is disabled.
     */
    private static function createExtensionCapabilitiesFromConfig(Config $config): ExtensionCapabilities
    {
        return $config->get(ConfigKey::MONITORING_ENABLED)
            ? new PotentiallyAvailableExtensionCapabilities()
            : new DoNotInvokeAnyExtensionCapabilities();
    }

    /**
     * The only required arguments are the {@see Config}, and a {@see LoggerInterface} implementation.
     *
     * @example
     *
     *     $agent = Agent::fromConfig(
     *       Config::fromArray([]), // Uses default configuration, which can be overridden with environment variables
     *       $logger // PSR-3 compatible logger, e.g. Monolog, or most framework loggers
     *     );
     *
     * If any of the other parameters are not provided, default configuration is used, which should be fine for most
     * usual cases. Default implementations are as follows:
     *
     *  - $cache - {@see DevNullCache} - i.e. no caching
     *  - $connector - {@see SocketConnector} - supports both UNIX sockets and TCP sockets
     *  - $extensionCapabilities - {@see PotentiallyAvailableExtensionCapabilities} or {@see DoNotInvokeAnyExtensionCapabilities} depending on configuration
     *  - $locateFileOrFolder - {@see LocateFileOrFolderUsingFilesystem}
     *  - $errorHandling - {@see ScoutErrorHandling} or {@see NoErrorHandling} depending on configuration
     */
    public static function fromConfig(
        Config $config,
        LoggerInterface $logger,
        ?CacheInterface $cache = null,
        ?Connector $connector = null,
        ?ExtensionCapabilities $extensionCapabilities = null,
        ?LocateFileOrFolder $locateFileOrFolder = null,
        ?ErrorHandling $errorHandling = null
    ): self {
        if (! $logger instanceof FilteredLogLevelDecorator) {
            $logger = new FilteredLogLevelDecorator(
                $logger,
                (string) $config->get(ConfigKey::LOG_LEVEL)
            );
        }

        $superglobals = SuperglobalsArrays::fromGlobalState();

        return new self(
            $config,
            $connector ?? self::createConnectorFromConfig($config, $logger),
            $logger,
            $extensionCapabilities ?? self::createExtensionCapabilitiesFromConfig($config),
            $cache ?? new DevNullCache(),
            $locateFileOrFolder ?? new LocateFileOrFolderUsingFilesystem(),
            $errorHandling ?? ErrorHandlingDiscoveryFactory::createAndListen($config, $logger, $superglobals),
            $superglobals,
            new FindRequestHeadersUsingServerGlobal($superglobals)
        );
    }

    private function extensionVersion(): string
    {
        $extensionVersion = $this->phpExtension->version();

        return $extensionVersion === null ? 'n/a' : $extensionVersion->toString();
    }

    private function checkExtensionVersion(): void
    {
        $extensionVersion = $this->phpExtension->version();

        if ($extensionVersion === null) {
            return;
        }

        $theMinimumRecommendedVersion = Version::fromString(self::WARN_WHEN_EXTENSION_IS_OLDER_THAN);

        if (! $extensionVersion->isOlderThan($theMinimumRecommendedVersion)) {
            return;
        }

        $this->logger->info(sprintf(
            'scoutapm PHP extension is currently %s, which is older than the minimum recommended version %s',
            $extensionVersion->toString(),
            $theMinimumRecommendedVersion->toString()
        ));
    }

    public function connect(): void
    {
        if (! $this->enabled()) {
            $this->logger->debug('Connection skipped, since monitoring is disabled');

            return;
        }

        $this->checkExtensionVersion();

        $scoutBannerMetadata = sprintf(
            'app=%s, ext=%s, lib=%s',
            $this->config->get(ConfigKey::APPLICATION_NAME),
            $this->extensionVersion(),
            ComposerPackagesCheck::phpLibraryVersion()
        );

        if (! $this->connector->connected()) {
            $this->logger->info(sprintf(
                'Scout Core Agent (%s) not connected yet, attempting to start',
                $scoutBannerMetadata
            ));
            $coreAgentDownloadPath = $this->config->get(ConfigKey::CORE_AGENT_DIRECTORY) . '/' . $this->config->get(ConfigKey::CORE_AGENT_FULL_NAME);
            $manager               = new AutomaticDownloadAndLaunchManager(
                $this->config,
                $this->logger,
                new Downloader(
                    $coreAgentDownloadPath,
                    $this->config->get(ConfigKey::CORE_AGENT_FULL_NAME),
                    $this->logger,
                    $this->config->get(ConfigKey::CORE_AGENT_DOWNLOAD_URL),
                    $this->config->get(ConfigKey::CORE_AGENT_PERMISSIONS)
                ),
                new Launcher(
                    $this->logger,
                    ConnectionAddress::fromConfig($this->config),
                    $this->config->get(ConfigKey::CORE_AGENT_LOG_LEVEL),
                    $this->config->get(ConfigKey::CORE_AGENT_LOG_FILE),
                    $this->config->get(ConfigKey::CORE_AGENT_CONFIG_FILE)
                ),
                new Verifier(
                    $this->logger,
                    $coreAgentDownloadPath
                )
            );
            $manager->launch();

            $this->phpExtension->clearRecordedCalls();

            // It's very likely the first request after first launch of core agent will fail, since we have to wait for
            // the agent to launch
            try {
                $this->connector->connect();

                $this->logger->debug('Connected to connector.');
            } catch (FailedToConnect $failedToConnect) {
                $this->logger->warning($failedToConnect->getMessage());
            }
        } else {
            $this->logger->debug(sprintf(
                'Scout Core Agent Connected (%s)',
                $scoutBannerMetadata
            ));
        }
    }

    /** {@inheritDoc} */
    public function enabled(): bool
    {
        return $this->config->get(ConfigKey::MONITORING_ENABLED);
    }

    /**
     * @param mixed $defaultReturn
     * @psalm-param callable(): ?T $codeToRunIfBelowSpanLimit
     *
     * @return mixed
     * @psalm-return ?T
     *
     * @psalm-template T
     */
    private function onlyRunIfBelowSpanLimit(callable $codeToRunIfBelowSpanLimit)
    {
        if ($this->spanLimitReached) {
            return null;
        }

        try {
            return $codeToRunIfBelowSpanLimit();
        } catch (SpanLimitReached $spanLimitReached) {
            $this->spanLimitReached = true;

            if ($this->request !== null) {
                $this->request->tag(Tag::TAG_REACHED_SPAN_CAP, true);
            }

            $this->logger->info($spanLimitReached->getMessage(), ['exception' => $spanLimitReached]);

            return null;
        }
    }

    /** {@inheritDoc} */
    public function startSpan(string $operation, ?float $overrideTimestamp = null, bool $leafSpan = false): ?SpanReference
    {
        $this->addSpansFromExtension();

        $returnValue = $this->onlyRunIfBelowSpanLimit(
            function () use ($operation, $overrideTimestamp, $leafSpan): ?Span {
                if ($this->request === null) {
                    return null;
                }

                return $this->request->startSpan($operation, $overrideTimestamp, $leafSpan);
            }
        );

        if ($returnValue === null) {
            return null;
        }

        return SpanReference::fromSpan($returnValue);
    }

    public function stopSpan(): void
    {
        if ($this->request === null) {
            return;
        }

        $this->addSpansFromExtension();

        $this->request->stopSpan();
    }

    private function addSpansFromExtension(): void
    {
        $this->onlyRunIfBelowSpanLimit(
            function (): ?Span {
                if ($this->request === null) {
                    return null;
                }

                foreach ($this->phpExtension->getCalls() as $recordedCall) {
                    $callSpan = $this->request->startSpan($recordedCall->functionName(), $recordedCall->timeEntered());

                    $maybeHttpUrl = $recordedCall->maybeHttpUrl();
                    if ($maybeHttpUrl !== null) {
                        $httpMethod = $recordedCall->maybeHttpMethod() ?: 'GET';
                        $httpSpan   = $this->request->startSpan('HTTP/' . $httpMethod, $recordedCall->timeEntered());
                        $httpSpan->tag(Tag::TAG_URI, $maybeHttpUrl);
                        $this->request->stopSpan($recordedCall->timeExited());
                    }

                    $arguments = $recordedCall->filteredArguments();

                    if (count($arguments) > 0) {
                        $callSpan->tag(Tag::TAG_ARGUMENTS, $arguments);
                    }

                    $this->request->stopSpan($recordedCall->timeExited());
                }

                return null;
            }
        );
    }

    /** {@inheritDoc} */
    public function instrument(string $type, string $name, callable $block)
    {
        $span = $this->startSpan($type . '/' . $name);

        try {
            return $block($span);
        } finally {
            if ($span !== null) {
                $this->stopSpan();
            }
        }
    }

    /** {@inheritDoc} */
    public function webTransaction(string $name, callable $block)
    {
        return $this->instrument(SpanReference::INSTRUMENT_CONTROLLER, $name, $block);
    }

    /** {@inheritDoc} */
    public function backgroundTransaction(string $name, callable $block)
    {
        return $this->instrument(SpanReference::INSTRUMENT_JOB, $name, $block);
    }

    public function addContext(string $tag, string $value): void
    {
        $this->tagRequest($tag, $value);
    }

    public function tagRequest(string $tag, string $value): void
    {
        if ($this->request === null) {
            return;
        }

        $this->request->tag($tag, $value);
    }

    /** {@inheritDoc} */
    public function ignored(string $path): bool
    {
        return $this->isIgnoredEndpoint($path) || $this->isIgnoredJob($path);
    }

    private function isIgnoredEndpoint(string $endpoint): bool
    {
        return $this->ignoredEndpoints->ignored($endpoint);
    }

    private function isIgnoredJob(string $job): bool
    {
        if ($this->ignoredJobs === null) {
            $ignoredJobsUnfiltered = $this->config->get(ConfigKey::IGNORED_JOBS);
            if (! is_array($ignoredJobsUnfiltered)) {
                $this->ignoredJobs = [];

                return false;
            }

            $this->ignoredJobs = array_values(array_filter(
                $ignoredJobsUnfiltered,
                /** @param mixed $item */
                static function ($item): bool {
                    return is_string($item) && $item !== '';
                }
            ));
        }

        foreach ($this->ignoredJobs as $ignoredJob) {
            if (stripos($job, $ignoredJob) === 0) {
                return true;
            }
        }

        return false;
    }

    /** {@inheritDoc} */
    public function ignore(?string $ignoreReason = null): void
    {
        $this->request      = null;
        $this->isIgnored    = true;
        $this->ignoreReason = $ignoreReason;
    }

    /** {@inheritDoc} */
    public function shouldInstrument(string $functionality): bool
    {
        $disabledInstruments = $this->config->get(ConfigKey::DISABLED_INSTRUMENTS);

        return $disabledInstruments === null
            || ! is_array($disabledInstruments)
            || ! in_array($functionality, $disabledInstruments, true);
    }

    /** {@inheritDoc} */
    public function changeRequestUri(string $newRequestUri): void
    {
        if ($this->request === null) {
            return;
        }

        $this->request->overrideRequestUri($newRequestUri);
    }

    public function recordThrowable(Throwable $throwable): void
    {
        $this->errorHandling->recordThrowable($throwable);
    }

    /** {@inheritDoc} */
    public function send(): bool
    {
        // Don't send if the agent is not enabled.
        if (! $this->enabled()) {
            $this->logger->debug('Not sending payload, monitoring is not enabled');

            return false;
        }

        // Don't send it if the request was ignored
        if ($this->isIgnored) {
            $this->logger->debug(sprintf(
                'Not sending payload, request has been ignored%s',
                $this->ignoreReason
                    ? sprintf(' (%s)', $this->ignoreReason)
                    : ''
            ));

            $this->startNewRequest();

            return false;
        }

        // Logic dictates that this can't happen, but static analysis would disagree since the annotation is nullable
        // $this->request is only null when the request has been ignored (for now).
        if ($this->request === null) {
            $this->logger->debug('Not sending payload, request was not set');

            // @todo throw exception? return false?
            return false;
        }

        if (! $this->connector->connected()) {
            try {
                $this->connector->connect();
                $this->logger->debug('Connected to connector whilst sending.');
            } catch (FailedToConnect $failedToConnect) {
                $this->logger->error($failedToConnect->getMessage());

                return false;
            }
        }

        try {
            $this->registerIfRequired();
            $this->sendMetadataIfRequired();

            $this->addSpansFromExtension();
            $this->request->stopIfRunning();

            $this->errorHandling->sendCollectedErrors();

            $shouldLogContent    = $this->config->get(ConfigKey::LOG_PAYLOAD_CONTENT);
            $collectedSpans      = $this->request->collectedSpans();
            $controllerOrJobName = $this->request->controllerOrJobName() ?: '(unknown)';

            $this->logger->debug(sprintf(
                'Sending metrics from request %s with %d collected spans.%s',
                $controllerOrJobName,
                $collectedSpans,
                $shouldLogContent ? sprintf(' Payload: %s', json_encode($this->request)) : ''
            ));

            $coreAgentResponse = $this->connector->sendCommand($this->request);

            $this->logger->debug(sprintf(
                'Sent whole payload successfully to core agent.%s',
                $shouldLogContent ? sprintf(' Response: %s', $coreAgentResponse) : ''
            ));

            $this->startNewRequest();

            return true;
        } catch (NotConnected $notConnected) {
            $this->logger->error($notConnected->getMessage());

            return false;
        } catch (FailedToSendCommand $failedToSendCommand) {
            $this->logger->log($failedToSendCommand->logLevel(), $failedToSendCommand->getMessage());

            return false;
        }
    }

    /** {@inheritDoc} */
    public function startNewRequest(): void
    {
        if ($this->request !== null) {
            $this->request->cleanUp();
        }

        $this->isIgnored    = false;
        $this->ignoreReason = null;

        $this->request = Request::fromConfigAndOverrideTime($this->superglobals, $this->config, $this->findRequestHeaders);

        $this->errorHandling->changeCurrentRequest($this->request);
    }

    private function registerIfRequired(): void
    {
        if ($this->registered) {
            return;
        }

        $this->connector->sendCommand(new RegisterMessage(
            (string) $this->config->get(ConfigKey::APPLICATION_NAME),
            (string) $this->config->get(ConfigKey::APPLICATION_KEY),
            (string) $this->config->get(ConfigKey::API_VERSION)
        ));

        $this->registered = true;
    }

    /** @throws Exception */
    private function sendMetadataIfRequired(): void
    {
        if ($this->metadataWasSent()) {
            $this->logger->debug('Skipping metadata send, already sent');

            return;
        }

        try {
            $this->connector->sendCommand(new Metadata(
                new DateTimeImmutable('now', new DateTimeZone('UTC')),
                $this->config,
                $this->phpExtension,
                new FindApplicationRootWithConfigOverride($this->locateFileOrFolder, $this->config, $this->superglobals),
                new DetermineHostnameWithConfigOverride($this->config),
                new FindRootPackageGitShaWithHerokuAndConfigOverride($this->config, $this->superglobals)
            ));

            $this->markMetadataSent();
        } catch (Throwable $exception) {
            $this->logger->notice(
                sprintf('Sending metadata raised an exception: %s', $exception->getMessage()),
                ['exception' => $exception]
            );
        }
    }

    private function metadataWasSent(): bool
    {
        return (bool) $this->cache->get(self::CACHE_KEY_METADATA_SENT, false);
    }

    private function markMetadataSent(): void
    {
        if ($this->metadataWasSent()) {
            return;
        }

        $this->cache->set(self::CACHE_KEY_METADATA_SENT, true, self::METADATA_CACHE_TTL_SECONDS);
    }

    /**
     * {@inheritDoc}
     *
     * @internal
     * @deprecated
     */
    public function getRequest(): ?Request
    {
        return $this->request;
    }
}
