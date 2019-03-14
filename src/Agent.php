<?php

namespace Scoutapm;

use Scoutapm\Events\Span;
use Scoutapm\Events\TagSpan;
use Scoutapm\Events\TagRequest;
use Scoutapm\Events\Request;
use Scoutapm\Helper\Timer;
use Scoutapm\Helper\Config;
use Scoutapm\Exception\Request\UnknownRequestException;

class Agent
{
    const VERSION = '1.0';

    const NAME = 'scoutapm-php';

    private $config;

    private $requestsStore;

    private $timer;

    public function __construct(array $config)
    {
        $this->config = new Config($config);
        $this->requestsStore = new RequestsStore();

        $this->timer = new Timer();
        $this->timer->start();
    }

    public function startRequest(string $name, float $startTime = null): Request
    {
        $this->requestsStore->register(
            new Request($name)
        );

        $request = $this->requestsStore->get($name);
        $request->start($startTime);

        return $request;
    }

    public function stopRequest(string $name)
    {
        $this->getRequest($name)->stop();
    }

    public function startSpan(string $name, string $requestName, float $startTime=null, string $parentSpanName=null) : Span
    {
        $request = $this->getRequest($requestName);
        $parentSpanId = null;
        if ($parentSpanName !== null) {
            $parentSpanId = $request->getSpan($parentSpanName)->getId();
        }
        $span = new Span($name, $request->getId(), $parentSpanId);
        $span->start($startTime);
        $request->setSpan($span);

        return $span;
    }

    public function stopSpan(string $name, string $requestName)
    {
        $request = $this->getRequest($requestName);
        $span = $request->getSpan($name);
        $span->stop();
        $request->setSpan($span);
    }

    public function tagSpan(string $requestName, string $tag, string $value, string $requestId, string $spanId, float $timestamp = null) : TagSpan
    {
        $request = $this->getRequest($requestName);
        $tagSpan = new TagSpan($tag, $value, $requestId, $spanId, $timestamp);
        $request->tagSpan($tagSpan);

        return $tagSpan;
    }

    public function tagRequest(string $requestName, string $tag, string $value, string $requestId, string $spanId, float $timestamp = null) : TagRequest
    {
        $request = $this->getRequest($requestName);
        $tagRequest = new TagRequest($tag, $value, $requestId, $spanId, $timestamp);
        $request->tagRequest($tagRequest);

        return $tagRequest;
    }

    public function getRequest(string $name)
    {
        $request = $this->requestsStore->get($name);
        if ($request === null) {
            throw new UnknownRequestException($name);
        }

        return $request;
    }

    public function getConfig() : \Scoutapm\Helper\Config
    {
        return $this->config;
    }

    public function send() : bool
    {
        if ($this->config->get('active') === false) {
            return true;
        }

        $connector = new Connector($this->config);

        if (!$this->requestsStore->isEmpty()) {
            $status = $connector->sendRequests($this->requestsStore);
            if (!$status) {
                $this->requestsStore->clear();
            }
        }

        return $status;
    }
}
