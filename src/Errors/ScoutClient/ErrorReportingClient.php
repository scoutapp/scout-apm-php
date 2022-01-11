<?php

declare(strict_types=1);

namespace Scoutapm\Errors\ScoutClient;

use Scoutapm\Errors\ErrorEvent;

/**
 * @internal This is not covered by BC promise
 */
interface ErrorReportingClient
{
    /**
     * @param ErrorEvent[] $errorEvents
     *
     * @psalm-param non-empty-list<ErrorEvent> $errorEvents
     */
    public function sendErrorToScout(array $errorEvents): void;
}
