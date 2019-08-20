<?php

declare(strict_types=1);

namespace Scoutapm\Events;

use Ramsey\Uuid\Uuid;
use Scoutapm\Agent;

/**
 * @internal
 * @deprecated Subclasses of this don't really share a common semantic meaning, this should be deprecated.
 */
abstract class Event
{
    /** @var Agent */
    protected $agent;

    /** @var string */
    protected $id;

    public function __construct(Agent $agent)
    {
        $this->agent = $agent;
        $this->id    = Uuid::uuid4()->toString();
    }

    public function getId() : string
    {
        return $this->id;
    }
}
