<?php

declare(strict_types=1);

namespace Scoutapm\Errors\ScoutClient;

use Scoutapm\Errors\ErrorEvent;

/**
 * @internal This is not covered by BC promise
 */
interface ErrorReportingClient
{
    public function sendErrorToScout(ErrorEvent $errorEvent): void; // @todo work out if we need to send multiple exceptions at once (probably...)
}
