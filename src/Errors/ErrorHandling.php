<?php

declare(strict_types=1);

namespace Scoutapm\Errors;

use Scoutapm\Events\Request\Request;

interface ErrorHandling
{
    public function changeCurrentRequest(Request $request): void;

    public function registerListeners(): void;

    public function sendCollectedErrors(): void;
}
