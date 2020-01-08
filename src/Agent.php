<?php

declare(strict_types=1);

namespace Scoutapm;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Scoutapm\Cache\DevNullCache;
use Scoutapm\Config\ConfigKey;
use Scoutapm\Config\IgnoredEndpoints;
use Scoutapm\Connector\Connector;
use Scoutapm\Connector\Exception\FailedToConnect;
use Scoutapm\Connector\Exception\FailedToSendCommand;
use Scoutapm\Connector\Exception\NotConnected;
use Scoutapm\Connector\SocketConnector;
use Scoutapm\CoreAgent\AutomaticDownloadAndLaunchManager;
use Scoutapm\CoreAgent\Downloader;
use Scoutapm\Events\Metadata;
use Scoutapm\Events\RegisterMessage;
use Scoutapm\Events\Request\Request;
use Scoutapm\Events\Request\RequestId;
use Scoutapm\Events\Span\Span;
use Scoutapm\Events\Tag\Tag;
use Scoutapm\Extension\ExtentionCapabilities;
use Scoutapm\Extension\PotentiallyAvailableExtensionCapabilities;
use Scoutapm\Extension\Version;
use Scoutapm\Logger\FilteredLogLevelDecorator;
use Throwable;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function json_encode;
use function sprintf;

final class Agent implements ScoutApmAgent
{
    private const CACHE_KEY_METADATA_SENT = 'scout_metadata_sent';

    private const WARN_WHEN_EXTENSION_IS_OLDER_THAN = '1.0.2';

    /** @var Config */
    private $config;

    /** @var Request|null */
    private $request;

    /** @var Connector */
    private $connector;

    /** @var LoggerInterface */
    private $logger;

    /**
     * Class that helps check incoming http paths vs. the configured ignore list
     *
     * @var IgnoredEndpoints
     */
    private $ignoredEndpoints;

    /**
     * If this request was marked as ignored
     *
     * @var bool
     */
    private $isIgnored = false;

    /** @var ExtentionCapabilities */
    private $phpExtension;

    /** @var CacheInterface */
    private $cache;

    /** @var bool */
    private $registered = false;

    private function __construct(
        Config $configuration,
        Connector $connector,
        LoggerInterface $logger,
        ExtentionCapabilities $phpExtension,
        CacheInterface $cache
    ) {
        $this->config       = $configuration;
        $this->connector    = $connector;
        $this->logger       = $logger;
        $this->phpExtension = $phpExtension;
        $this->cache        = $cache;

        if (! $this->logger instanceof FilteredLogLevelDecorator) {
            $this->logger = new FilteredLogLevelDecorator(
                $this->logger,
                $this->config->get(ConfigKey::LOG_LEVEL)
            );
        }

        if ($this->config->get(ConfigKey::MONITORING_ENABLED)) {
            $this->warnIfConfigValueIsNotSet(ConfigKey::APPLICATION_NAME);
            $this->warnIfConfigValueIsNotSet(ConfigKey::APPLICATION_KEY);
        }

        $this->startNewRequest();

        $this->ignoredEndpoints = new IgnoredEndpoints($configuration->get(ConfigKey::IGNORED_ENDPOINTS));
    }

    private function warnIfConfigValueIsNotSet(string $configKey) : void
    {
        $configValue = $this->config->get($configKey);

        if ($configValue !== null && (! is_string($configValue) || $configValue !== '')) {
            return;
        }

        $this->logger->warning(sprintf('Config key "%s" should be set, but it was empty', $configKey));
    }

    private static function createConnectorFromConfig(Config $config) : SocketConnector
    {
        return new SocketConnector(
            $config->get(ConfigKey::CORE_AGENT_SOCKET_PATH),
            $config->get(ConfigKey::MONITORING_ENABLED)
        );
    }

    public static function fromConfig(
        Config $config,
        LoggerInterface $logger,
        ?CacheInterface $cache = null,
        ?Connector $connector = null,
        ?ExtentionCapabilities $extentionCapabilities = null
    ) : self {
        return new self(
            $config,
            $connector ?? self::createConnectorFromConfig($config),
            $logger,
            $extentionCapabilities ?? new PotentiallyAvailableExtensionCapabilities(),
            $cache ?? new DevNullCache()
        );
    }

    private function extensionVersion() : string
    {
        $extensionVersion = $this->phpExtension->version();

        return $extensionVersion === null ? 'n/a' : $extensionVersion->toString();
    }

    private function checkExtensionVersion() : void
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

    public function connect() : void
    {
        $this->logger->debug('Configuration: ' . json_encode($this->config->asArrayWithSecretsRemoved()));

        if (! $this->enabled()) {
            $this->logger->debug('Connection skipped, since monitoring is disabled');

            return;
        }

        $this->checkExtensionVersion();

        if (! $this->connector->connected()) {
            $this->logger->info(sprintf(
                'Scout Core Agent (app=%s, ext=%s) not connected yet, attempting to start',
                $this->config->get(ConfigKey::APPLICATION_NAME),
                $this->extensionVersion()
            ));
            $manager = new AutomaticDownloadAndLaunchManager(
                $this->config,
                $this->logger,
                new Downloader(
                    $this->config->get(ConfigKey::CORE_AGENT_DIRECTORY) . '/' . $this->config->get(ConfigKey::CORE_AGENT_FULL_NAME),
                    $this->config->get(ConfigKey::CORE_AGENT_FULL_NAME),
                    $this->logger,
                    $this->config->get(ConfigKey::CORE_AGENT_DOWNLOAD_URL),
                    $this->config->get(ConfigKey::CORE_AGENT_PERMISSIONS)
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
                'Scout Core Agent Connected (app=%s, ext=%s)',
                $this->config->get(ConfigKey::APPLICATION_NAME),
                $this->extensionVersion()
            ));
        }
    }

    /** {@inheritDoc} */
    public function enabled() : bool
    {
        return $this->config->get(ConfigKey::MONITORING_ENABLED);
    }

    /** {@inheritDoc} */
    public function startSpan(string $operation, ?float $overrideTimestamp = null) : Span
    {
        if ($this->request === null) {
            // Must return a Span object to match API. This is a dummy span
            // that is not ever used for anything.
            return new Span(new Request(), 'Ignored', RequestId::new());
        }

        $this->addSpansFromExtension();

        return $this->request->startSpan($operation, $overrideTimestamp);
    }

    public function stopSpan() : void
    {
        if ($this->request === null) {
            return;
        }

        $this->addSpansFromExtension();

        $this->request->stopSpan();
    }

    private function addSpansFromExtension() : void
    {
        if ($this->request === null) {
            return;
        }

        foreach ($this->phpExtension->getCalls() as $recordedCall) {
            $callSpan = $this->request->startSpan($recordedCall->functionName(), $recordedCall->timeEntered());

            $arguments = $recordedCall->filteredArguments();

            if (count($arguments) > 0) {
                $callSpan->tag(Tag::TAG_ARGUMENTS, $arguments);
            }

            $this->request->stopSpan($recordedCall->timeExited());
        }
    }

    /** {@inheritDoc} */
    public function instrument(string $type, string $name, Closure $block)
    {
        $span = $this->startSpan($type . '/' . $name);

        try {
            return $block($span);
        } finally {
            $this->stopSpan();
        }
    }

    /** {@inheritDoc} */
    public function webTransaction(string $name, Closure $block)
    {
        return $this->instrument(Span::INSTRUMENT_CONTROLLER, $name, $block);
    }

    /** {@inheritDoc} */
    public function backgroundTransaction(string $name, Closure $block)
    {
        return $this->instrument(Span::INSTRUMENT_JOB, $name, $block);
    }

    public function addContext(string $tag, string $value) : void
    {
        $this->tagRequest($tag, $value);
    }

    public function tagRequest(string $tag, string $value) : void
    {
        if ($this->request === null) {
            return;
        }

        $this->request->tag($tag, $value);
    }

    /** {@inheritDoc} */
    public function ignored(string $path) : bool
    {
        return $this->ignoredEndpoints->ignored($path);
    }

    /** {@inheritDoc} */
    public function ignore() : void
    {
        $this->request   = null;
        $this->isIgnored = true;
    }

    /** {@inheritDoc} */
    public function shouldInstrument(string $functionality) : bool
    {
        $disabledInstruments = $this->config->get(ConfigKey::DISABLED_INSTRUMENTS);

        return $disabledInstruments === null
            || ! is_array($disabledInstruments)
            || ! in_array($functionality, $disabledInstruments, true);
    }

    /** {@inheritDoc} */
    public function changeRequestUri(string $newRequestUri) : void
    {
        if ($this->request === null) {
            return;
        }
        $this->request->overrideRequestUri($newRequestUri);
    }

    /** {@inheritDoc} */
    public function send() : bool
    {
        // Don't send if the agent is not enabled.
        if (! $this->enabled()) {
            $this->logger->debug('Not sending payload, monitoring is not enabled');

            return false;
        }

        // Don't send it if the request was ignored
        if ($this->isIgnored) {
            $this->logger->debug('Not sending payload, request has been ignored');

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

            $this->request->stopIfRunning();

            $this->logger->debug(sprintf('Sending metrics from %d collected spans', $this->request->collectedSpans()));

            $this->logger->debug(sprintf(
                'Sent whole payload successfully to core agent. Core agent response was: %s',
                $this->connector->sendCommand($this->request)
            ));

            $this->startNewRequest();

            return true;
        } catch (NotConnected $notConnected) {
            $this->logger->error($notConnected->getMessage());

            return false;
        } catch (FailedToSendCommand $failedToSendCommand) {
            $this->logger->error($failedToSendCommand->getMessage());

            return false;
        }
    }

    /** {@inheritDoc} */
    public function startNewRequest() : void
    {
        $this->request = new Request();
    }

    private function registerIfRequired() : void
    {
        if ($this->registered) {
            return;
        }

        $this->connector->sendCommand(new RegisterMessage(
            (string) $this->config->get(ConfigKey::APPLICATION_NAME),
            (string) $this->config->get(ConfigKey::APPLICATION_KEY),
            $this->config->get(ConfigKey::API_VERSION)
        ));

        $this->registered = true;
    }

    /**
     * @throws Exception
     */
    private function sendMetadataIfRequired() : void
    {
        if ($this->metadataWasSent()) {
            $this->logger->debug('Skipping metadata send, already sent');

            return;
        }

        try {
            $this->connector->sendCommand(new Metadata(
                new DateTimeImmutable('now', new DateTimeZone('UTC')),
                $this->config,
                $this->phpExtension
            ));

            $this->markMetadataSent();
        } catch (Throwable $exception) {
            $this->logger->notice(
                sprintf('Sending metadata raised an exception: %s', $exception->getMessage()),
                ['exception' => $exception]
            );
        }
    }

    private function metadataWasSent() : bool
    {
        return (bool) $this->cache->get(self::CACHE_KEY_METADATA_SENT, false);
    }

    private function markMetadataSent() : void
    {
        if ($this->metadataWasSent()) {
            return;
        }

        $this->cache->set(self::CACHE_KEY_METADATA_SENT, true);
    }

    /**
     * {@inheritDoc}
     *
     * @internal
     * @deprecated
     */
    public function getRequest() : ?Request
    {
        return $this->request;
    }
}
