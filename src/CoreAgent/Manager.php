<?php

declare(strict_types=1);

namespace Scoutapm\CoreAgent;

interface Manager
{
    public function launch(): bool;
}
