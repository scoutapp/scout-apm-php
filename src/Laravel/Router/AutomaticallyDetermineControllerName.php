<?php

declare(strict_types=1);

namespace Scoutapm\Laravel\Router;

use Illuminate\Http\Request;

interface AutomaticallyDetermineControllerName
{
    public function __invoke(Request $request): string;
}
