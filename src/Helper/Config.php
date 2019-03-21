<?php

namespace Scoutapm\Helper;

use Scoutapm\Exception\MissingAppNameException;
use Scoutapm\Exception\MissingKeyException;

class Config
{
    private $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    public function get(string $key)
    {
        return ($this->config[$key]) ?? null;
    }

    public function asArray() : array
    {
        return $this->config;
    }

    private function getDefaultConfig() : array
    {
        return [
            'appName' => null,
            'key' => null,
            'apiVersion'  => '1.0',
            'socketLocation'   => '/tmp/core-agent.sock',
        ];
    }
}
