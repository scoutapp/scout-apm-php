<?php

namespace Scoutapm;

use Closure;
use Psr\Log\NullLogger;
use Scoutapm\Events\Request;
use Scoutapm\Events\Span;
use Scoutapm\Events\TagRequest;

class Agent
{
    const VERSION = '1.0';

    const NAME = 'scout-apm-php';

    private $config;

    private $request;

    /** @var Connector|null */
    private $connector;

    private $logger;

    // Class that helps check incoming http paths vs. the configured ignore list
    private $ignoredEndpoints;

    // If this request was marked as ignored
    private $ignored;

    public function __construct()
    {
        $this->config = new Config($this);
        $this->request = new Request($this);
        $this->logger = new NullLogger();

        $this->ignoredEndpoints = new IgnoredEndpoints($this);
        $this->ignored = false;
    }

    public function connect()
    {
        $this->connector = new Connector($this);
        if (! $this->connector->connected() && $this->enabled()) {
            $this->logger->info("Scout Core Agent Connection Failed, attempting to start");
            $manager = new CoreAgentManager($this);
            $manager->launch();

            $this->connector->connect();
        } else {
            $this->logger->debug("Scout Core Agent Connected");
        }
    }

    // Returns true/false on if the agent should attempt to start and collect data.
    public function enabled() : bool
    {
        return $this->config->get('monitor');
    }

    /**
     * Sets the logger for the Agent to use
     *
     * @return void
     */
    public function setLogger(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function setConfig(Config $config)
    {
        $this->config = $config;
    }

    /**
     * returns the active logger
     *
     * @return \Psr\Log\LoggerInterface compliant logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * returns the active configuration
     *
     * @return \Scoutapm\Config
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
     * @param operation The "name" of the span, something like "Controller/User" or "SQL/Query"
     * @param overrideTimestamp if you need to set the start time to something specific
     *
     * @return Span
     */
    public function startSpan(string $operation, float $overrideTimestamp = null)
    {
        if ($this->request === null) {
            // Must return a Span object to match API. This is a dummy span
            // that is not ever used for anything.
            return new Span($this, "Ignored", "ignored-request");
        }

        return $this->request->startSpan($operation, $overrideTimestamp);
    }

    public function stopSpan()
    {
        if ($this->request === null) {
            return null;
        }

        $this->request->stopSpan();
    }

    public function instrument($type, $name, Closure $block)
    {
        $span = $this->startSpan($type . "/" . $name);

        try {
            return $block($span);
        } finally {
            $this->stopSpan();
        }
    }

    public function webTransaction($name, Closure $block)
    {
        return $this->instrument("Controller", $name, $block);
    }

    public function backgroundTransaction($name, Closure $block)
    {
        return $this->instrument("Job", $name, $block);
    }

    public function addContext(string $tag, $value)
    {
        return $this->tagRequest($tag, $value);
    }

    public function tagRequest(string $tag, $value)
    {
        if ($this->request === null) {
            return null;
        }

        return $this->request->tag($tag, $value);
    }

    /*
     * Check if a given URL was configured as ignored.
     * Does not alter the running request. If you wish to abort tracing of this
     * request, use ignore()
     */
    public function ignored(string $path) : bool
    {
        return $this->ignoredEndpoints->ignored($path);
    }

    /*
     * Mark the running request as ignored. Triggers optimizations in various
     * tracing and tagging methods to turn them into NOOPs
     */
    public function ignore()
    {
        $this->request = null;
        $this->ignored = true;
    }

    // Returns true only if the request was sent onward to the core agent.
    // False otherwise.
    public function send() : bool
    {
        // Don't send if the agent is not enabled.
        if (! $this->enabled()) {
            return false;
        }

        // Don't send it if the request was ignored
        if ($this->ignored()) {
            return false;
        }

        // Send this request off to the CoreAgent
        $status = $this->connector->sendRequest($this->request);
        return $status;
    }

    /**
     * You probably don't need this, it's useful in testing
     *
     * @return Request
     */
    public function getRequest() : \Scoutapm\Events\Request
    {
        return $this->request;
    }
}
