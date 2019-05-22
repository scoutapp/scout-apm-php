<?php

namespace Scoutapm;

use Scoutapm\Events\Span;
use Scoutapm\Events\TagSpan;
use Scoutapm\Events\TagRequest;
use Scoutapm\Events\Request;
use Scoutapm\Config;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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

    public function startSpan(string $name, float $startTime = null)
    {
        $span = new Span($this, $name);
        $span->start($startTime);
        $this->request->addSpan($span);
    }

    public function stopSpan()
    {
        $this->request->stopSpan();
    }

    public function tagSpan(string $tag, string $value, float $timestamp = null, bool $current = true)
    {
        $tagSpan = new TagSpan($this, $tag, $value, $timestamp);
        $this->request->tagSpan($tagSpan, $current);
    }

    public function tagRequest(string $tag, string $value, float $timestamp = null)
    {
        $tagRequest = new TagRequest($this, $tag, $value, $timestamp);
        $this->request->tagRequest($tagRequest);
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
}
