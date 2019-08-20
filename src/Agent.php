<?php

declare(strict_types=1);

namespace Scoutapm;

use Closure;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Scoutapm\Events\Request;
use Scoutapm\Events\Span;

class Agent
{
    public const VERSION = '1.0';

    public const NAME = 'scout-apm-php';

    /** @var Config */
    private $config;

    /** @var Request */
    private $request;

    /** @var Connector|null */
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
    private $isIgnored;

    public function __construct()
    {
        $this->config  = new Config($this);
        $this->request = new Request($this);
        $this->logger  = new NullLogger();

        $this->ignoredEndpoints = new IgnoredEndpoints($this);
        $this->isIgnored        = false;
    }

    public function connect() : void
    {
        $this->connector = new Connector($this);
        if (! $this->connector->connected() && $this->enabled()) {
            $this->logger->info('Scout Core Agent Connection Failed, attempting to start');
            $manager = new CoreAgentManager($this);
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
     */
    public function setLogger(LoggerInterface $logger) : void
    {
        $this->logger = $logger;
    }

    public function setConfig(Config $config) : void
    {
        $this->config = $config;
    }

    /**
     * returns the active logger
     *
     * @return LoggerInterface compliant logger
     */
    public function getLogger() : LoggerInterface
    {
        return $this->logger;
    }

    /**
     * returns the active configuration
     */
    public function getConfig() : Config
    {
        return $this->config;
    }

    /**
     * Starts a new span on the current request.
     *
     * NOTE: Do not call stop on the span itself, use the stopSpan() function
     * here to ensure the whole system knows its stopped
     *
     * @param string $operation         The "name" of the span, something like "Controller/User" or "SQL/Query"
     * @param ?float $overrideTimestamp If you need to set the start time to something specific
     */
    public function startSpan(string $operation, ?float $overrideTimestamp = null) : Span
    {
        if ($this->request === null) {
            // Must return a Span object to match API. This is a dummy span
            // that is not ever used for anything.
            return new Span($this, 'Ignored', 'ignored-request');
        }

        return $this->request->startSpan($operation, $overrideTimestamp);
    }

    public function stopSpan() : void
    {
        if ($this->request === null) {
            return null;
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

        // Send this request off to the CoreAgent
        return $this->connector->sendRequest($this->request);
    }

    /**
     * You probably don't need this, it's useful in testing
     */
    public function getRequest() : Request
    {
        return $this->request;
    }
}
