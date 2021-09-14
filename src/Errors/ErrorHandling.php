<?php

declare(strict_types=1);

namespace Scoutapm\Errors;

use Scoutapm\Events\Request\RequestId;

interface ErrorHandling
{
    public function changeCurrentRequestId(RequestId $requestId): void;

    public function registerListeners(): void;

    public function sendCollectedErrors(): void;
}
