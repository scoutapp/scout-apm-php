<?php

declare(strict_types=1);

namespace Scoutapm;

use Closure;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Scoutapm\Config\IgnoredEndpoints;
use Scoutapm\Connector\Connector;
use Scoutapm\Connector\SocketConnector;
use Scoutapm\CoreAgent\Downloader;
use Scoutapm\CoreAgent\AutomaticDownloadAndLaunchManager;
use Scoutapm\Events\Request\Request;
use Scoutapm\Events\Request\RequestId;
use Scoutapm\Events\Span\Span;

// @todo needs interface
final class Agent
{
    public const VERSION = '1.0';

    public const NAME = 'scout-apm-php';

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

    public function __construct(Config $configuration, Connector $connector, LoggerInterface $logger)
    {
        $this->config    = $configuration;
        $this->connector = $connector;
        $this->logger    = $logger;

        $this->request = new Request();

        $this->ignoredEndpoints = new IgnoredEndpoints($configuration->get('ignore') ?: []);
    }

    private static function createConnectorFromConfig(Config $config) : SocketConnector
    {
        return new SocketConnector(
            $config->get('socket_path'),
            (string) $config->get('name'),
            (string) $config->get('key'),
            $config->get('api_version')
        );
    }

    /**
     * @deprecated Once getConfig is removed, you cannot overwrite config using this...
     */
    public static function fromDefaults(?LoggerInterface $logger = null, ?Connector $connector = null) : self
    {
        $config = new Config();

        return new self(
            $config,
            $connector ?? self::createConnectorFromConfig($config),
            $logger ?? new NullLogger()
        );
    }

    public static function fromConfig(Config $config, ?LoggerInterface $logger = null, ?Connector $connector = null) : self
    {
        return new self(
            $config,
            $connector ?? self::createConnectorFromConfig($config),
            $logger ?? new NullLogger()
        );
    }

    public function connect() : void
    {
        if (! $this->connector->connected() && $this->enabled()) {
            $this->logger->info('Scout Core Agent Connection Failed, attempting to start');
            $manager = new AutomaticDownloadAndLaunchManager(
                $this->config,
                $this->logger,
                new Downloader(
                    $this->config->get('core_agent_dir'),
                    $this->config->get('core_agent_full_name'),
                    $this,
                    $this->config->get('download_url')
                )
            );
            $manager->launch();

            $this->connector->connect();
        } else {
            $this->logger->debug('Scout Core Agent Connected');
        }
    }

    /**
     * Returns true/false on if the agent should attempt to start and collect data.
     */
    public function enabled() : bool
    {
        return $this->config->get('monitor');
    }

    /**
     * Sets the logger for the Agent to use
     *
     * @deprecated move to constructor injection
     */
    public function setLogger(LoggerInterface $logger) : void
    {
        $this->logger = $logger;
    }

    /**
     * @deprecated move to constructor injection
     */
    public function setConfig(Config $config) : void
    {
        $this->config = $config;
    }

    /**
     * returns the active logger
     *
     * @deprecated will be deleted, do not rely on this externally - consumers within the library should have Logger injected
     *
     * @return LoggerInterface compliant logger
     *
     * @private
     */
    public function getLogger() : LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Starts a fromRequest span on the current request.
     *
     * NOTE: Do not call stop on the span itself, use the stopSpan() function
     * here to ensure the whole system knows its stopped
     *
     * @param string $operation         The "name" of the span, something like "Controller/User" or "SQL/Query"
     * @param ?float $overrideTimestamp If you need to set the start time to something specific
     *
     * @throws Exception
     */
    public function startSpan(string $operation, ?float $overrideTimestamp = null) : Span
    {
        if ($this->request === null) {
            // Must return a Span object to match API. This is a dummy span
            // that is not ever used for anything.
            return new Span('Ignored', RequestId::new());
        }

        return $this->request->startSpan($operation, $overrideTimestamp);
    }

    public function stopSpan() : void
    {
        if ($this->request === null) {
            return;
        }

        $this->request->stopSpan();
    }

    /** @return mixed */
    public function instrument(string $type, string $name, Closure $block)
    {
        $span = $this->startSpan($type . '/' . $name);

        try {
            return $block($span);
        } finally {
            $this->stopSpan();
        }
    }

    /** @return mixed */
    public function webTransaction(string $name, Closure $block)
    {
        return $this->instrument('Controller', $name, $block);
    }

    /** @return mixed */
    public function backgroundTransaction(string $name, Closure $block)
    {
        return $this->instrument('Job', $name, $block);
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

    /**
     * Check if a given URL was configured as ignored.
     * Does not alter the running request. If you wish to abort tracing of this
     * request, use ignore()
     */
    public function ignored(string $path) : bool
    {
        return $this->ignoredEndpoints->ignored($path);
    }

    /**
     * Mark the running request as ignored. Triggers optimizations in various
     * tracing and tagging methods to turn them into NOOPs
     */
    public function ignore() : void
    {
        $this->request   = null;
        $this->isIgnored = true;
    }

    /**
     * Returns true only if the request was sent onward to the core agent. False otherwise.
     *
     * @throws Exception
     */
    public function send() : bool
    {
        // Don't send if the agent is not enabled.
        if (! $this->enabled()) {
            return false;
        }

        // Don't send it if the request was ignored
        if ($this->isIgnored) {
            return false;
        }

        if ($this->request === null) {
            // @todo throw exception? return false?
            return false;
        }

        // Send this request off to the CoreAgent
        return $this->connector->sendRequest($this->request);
    }

    /**
     * You probably don't need this, it's useful in testing
     *
     * @internal
     * @deprecated
     */
    public function getRequest() : ?Request
    {
        return $this->request;
    }
}
