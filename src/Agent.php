<?php

declare(strict_types=1);

namespace Scoutapm;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
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
use Scoutapm\Logger\FilteredLogLevelDecorator;
use function is_string;
use function json_encode;
use function sprintf;

final class Agent implements ScoutApmAgent
{
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

    public function __construct(Config $configuration, Connector $connector, LoggerInterface $logger, ExtentionCapabilities $phpExtension)
    {
        $this->config       = $configuration;
        $this->connector    = $connector;
        $this->logger       = $logger;
        $this->phpExtension = $phpExtension;

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

        $this->request = new Request();

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
        return new SocketConnector($config->get(ConfigKey::CORE_AGENT_SOCKET_PATH));
    }

    /**
     * @deprecated Once getConfig is removed, you cannot overwrite config using this...
     *
     * @todo alternative API to be discussed...
     */
    public static function fromDefaults(?LoggerInterface $logger = null, ?Connector $connector = null) : self
    {
        $config = new Config();

        return new self(
            $config,
            $connector ?? self::createConnectorFromConfig($config),
            $logger ?? new NullLogger(),
            new PotentiallyAvailableExtensionCapabilities()
        );
    }

    public static function fromConfig(Config $config, ?LoggerInterface $logger = null, ?Connector $connector = null) : self
    {
        return new self(
            $config,
            $connector ?? self::createConnectorFromConfig($config),
            $logger ?? new NullLogger(),
            new PotentiallyAvailableExtensionCapabilities()
        );
    }

    public function connect() : void
    {
        $this->logger->debug('Configuration: ' . json_encode($this->config->asArrayWithSecretsRemoved()));

        if (! $this->enabled()) {
            $this->logger->debug('Connection skipped, since monitoring is disabled');

            return;
        }

        if (! $this->connector->connected()) {
            $this->logger->info(sprintf(
                'Scout Core Agent (app=%s) not connected yet, attempting to start',
                $this->config->get(ConfigKey::APPLICATION_NAME)
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
                'Scout Core Agent Connected (app=%s)',
                $this->config->get(ConfigKey::APPLICATION_NAME)
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
            $callSpan->tag(Tag::TAG_ARGUMENTS, $recordedCall->filteredArguments());
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
            $this->logger->debug('Not sending payload, logging is not enabled');

            return false;
        }

        // Don't send it if the request was ignored
        if ($this->isIgnored) {
            $this->logger->debug('Not sending payload, request has been ignored');

            return false;
        }

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
            $this->connector->sendCommand(new RegisterMessage(
                (string) $this->config->get(ConfigKey::APPLICATION_NAME),
                (string) $this->config->get(ConfigKey::APPLICATION_KEY),
                $this->config->get(ConfigKey::API_VERSION)
            ));

            $this->connector->sendCommand(new Metadata(
                new DateTimeImmutable('now', new DateTimeZone('UTC')),
                $this->config
            ));

            $this->request->stopIfRunning();

            $this->logger->debug(sprintf('Sending metrics from %d collected spans', $this->request->collectedSpans()));

            $this->logger->debug(sprintf(
                'Sent whole payload successfully to core agent. Core agent response was: %s',
                $this->connector->sendCommand($this->request)
            ));

            return true;
        } catch (NotConnected $notConnected) {
            $this->logger->error($notConnected->getMessage());

            return false;
        } catch (FailedToSendCommand $failedToSendCommand) {
            $this->logger->error($failedToSendCommand->getMessage());

            return false;
        }
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
