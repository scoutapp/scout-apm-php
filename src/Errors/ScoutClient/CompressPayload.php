<?php

declare(strict_types=1);

namespace Scoutapm\Errors\ScoutClient;

use function gzencode;

/**
 * @internal This is not covered by BC promise
 */
class CompressPayload
{
    public function __invoke(string $payload): string
    {
        return gzencode($payload);
    }
}
