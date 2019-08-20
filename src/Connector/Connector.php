<?php

declare(strict_types=1);

namespace Scoutapm\Connector;

use Scoutapm\Events\Request;

/** @internal */
interface Connector
{
    public function connect() : void;

    public function connected() : bool;

    public function sendRequest(Request $request) : bool;

    public function shutdown() : void;
}
