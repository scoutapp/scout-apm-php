<?php

declare(strict_types=1);

namespace Scoutapm\Connector;

interface CommandWithChildren extends Command
{
    public function appendChild(Command $command): void;
}
