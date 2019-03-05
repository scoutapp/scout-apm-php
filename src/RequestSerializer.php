<?php

namespace Scoutapm;

use Scoutapm\Helper\Config;

class RequestSerializer implements \JsonSerializable
{
    protected $config;

    private $store;

    public function __construct(Config $config, RequestsStore $store)
    {
        $this->config = $config;
        $this->store = $store;
    }

    public function jsonSerialize()
    {
        $data = json_decode(json_encode($this->store, true));
        $data = array_shift($data);

        $commands = array();
        $dates = array();

        foreach ($data as $command) {
            $commands[] = $command;
            $dates[] = (int) \DateTime::createFromFormat('Y-m-d\TH:i:s.uP', reset($command)->timestamp)->format('Uu');
        }
        
        array_multisort($dates, SORT_ASC, $commands);

        return [
            'BatchCommand' => [
                'commands' => $commands,
            ]
        ];
    }
}
