<?php

namespace Scoutapm;

use Scoutapm\Events\Span;
use Scoutapm\Events\TagSpan;
use Scoutapm\Events\TagRequest;
use Scoutapm\Events\Request;
use Scoutapm\Helper\Config;
use Scoutapm\Exception\Request\UnknownRequestException;

class Agent
{
    const VERSION = '1.0';

    const NAME = 'scoutapm-php';

    private $config;

    private $request;

    public function __construct(array $config)
    {
        $this->config = new Config($config);
        $this->request = new Request('Request');
    }

    public function startSpan(string $name, float $startTime=null)
    {
        $span = new Span($name);
        $span->start($startTime);
        $this->request->addSpan($span);
    }

    public function stopSpan()
    {
        $this->request->stopSpan();
    }

    public function tagSpan(string $tag, string $value, bool $current = true, float $timestamp = null)
    {
        $tagSpan = new TagSpan($tag, $value, $timestamp);
        $this->request->addTag($tagSpan);
    }

    public function tagRequest(string $tag, string $value, float $timestamp = null)
    {
        $tagRequest = new TagRequest($tag, $value, $timestamp);
        $this->request->tagRequest($tagRequest);
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
