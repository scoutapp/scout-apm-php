<?php

namespace Scoutapm\Events;

use Ramsey\Uuid\Uuid;

abstract class Event
{
    protected $agent;

    protected $id;

    public function __construct(\Scoutapm\Agent $agent)
    {
        $this->agent = $agent;
        $this->id = Uuid::uuid4()->toString();
    }

    public function getId() : string
    {
        return $this->id;
    }
}
