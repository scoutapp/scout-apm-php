<?php

declare(strict_types=1);

namespace Scoutapm\Errors;

use Scoutapm\Events\Request\Request;
use Throwable;

/**
 * @internal This is not covered by BC promise
 */
final class NoErrorHandling implements ErrorHandling
{
    public function changeCurrentRequest(Request $request): void
    {
    }

    public function registerListeners(): void
    {
    }

    public function sendCollectedErrors(): void
    {
    }

    public function recordThrowable(Throwable $throwable): void
    {
    }
}
