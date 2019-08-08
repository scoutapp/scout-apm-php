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

    const NAME = 'scoutapm-php';

    private $config;

    private $request;

    private $connector;

    private $logger;

    public function __construct()
    {
        $this->config = new Config($this);
        $this->request = new Request($this);
        $this->logger = new NullLogger();
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
        return $this->request->startSpan($operation, $overrideTimestamp);
    }
    
    public function stopSpan()
    {
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
        return $this->request->tag($tag, $value);
    }

    public function send() : bool
    {
        if (! $this->enabled()) {
            return true;
        }

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
