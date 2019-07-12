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

    private $logger;

    public function __construct()
    {
        $this->config = new Config($this);
        $this->request = new Request($this, 'Request');
        $this->logger = new NullLogger();
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

    public function tagRequest(string $tag, string $value)
    {
        $this->request->tag($tag, $value);
    }

    public function send() : bool
    {
        if ($this->config->get('active') === false) {
            return true;
        }

        $connector = new Connector($this);

        $status = $connector->sendRequest($this->request);

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
