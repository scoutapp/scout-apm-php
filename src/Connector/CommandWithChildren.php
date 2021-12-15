<?php

declare(strict_types=1);

namespace Scoutapm\Connector;

interface CommandWithChildren extends Command
{
    /** @no-named-arguments */
    public function appendChild(Command $command): void;
}
