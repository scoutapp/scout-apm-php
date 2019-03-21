<?php

namespace Scoutapm;

use Scoutapm\Events\Request;
use Scoutapm\Helper\Config;

class RequestSerializer implements \JsonSerializable
{
    protected $config;

    private $request;

    public function __construct(Config $config, Request $request)
    {
        $this->config = $config;
        $this->request = $request;
    }

    public function jsonSerialize()
    {
        $data = json_decode(json_encode($this->request, true));

        return [
            'BatchCommand' => [
                'commands' => $data,
            ]
        ];
    }
}
