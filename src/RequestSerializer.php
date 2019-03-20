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
        $data = array_shift($data);

        $commands = [];
        $dates = [];

        foreach ($data as $command) {
            $commands[] = $command;

            $dates[] = (int) \DateTime::createFromFormat('Y-m-d\TH:i:s.uP', $command->timestamp)->format('Uu');
        }
        
        array_multisort($dates, SORT_ASC, $commands);

        return [
            'BatchCommand' => [
                'commands' => $commands,
            ]
        ];
    }
}
