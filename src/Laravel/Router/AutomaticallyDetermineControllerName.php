<?php

declare(strict_types=1);

namespace Scoutapm\Laravel\Router;

use Illuminate\Http\Request;

interface AutomaticallyDetermineControllerName
{
    /** @no-named-arguments */
    public function __invoke(Request $request): string;
}
