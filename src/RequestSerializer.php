<?php

namespace Scoutapm;

use Scoutapm\Events\Request;

class RequestSerializer implements \JsonSerializable
{
    private $request;

    public function __construct(Request $request)
    {
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
