<?php

declare(strict_types=1);

namespace Scoutapm\Connector;

use JsonSerializable;

interface Command extends JsonSerializable
{
    /**
     * __destruct does not work in removing the cyclic references here, so calling this to remove these references
     * allows GC to unset stuff to ensure it's really removed.
     */
    public function cleanUp(): void;
}
