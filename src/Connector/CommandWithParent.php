<?php

declare(strict_types=1);

namespace Scoutapm\Connector;

interface CommandWithParent extends Command
{
    public function parent(): CommandWithChildren;
}
