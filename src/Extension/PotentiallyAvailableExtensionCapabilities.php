<?php

declare(strict_types=1);

namespace Scoutapm\Extension;

use function array_map;
use function extension_loaded;
use function function_exists;
use function scoutapm_get_calls;

final class PotentiallyAvailableExtensionCapabilities implements ExtentionCapabilities
{
    /** @return RecordedCall[]|array<int, RecordedCall> */
    public function getCalls() : array
    {
        if (! $this->extensionIsAvailable()) {
            return [];
        }

        /** @psalm-suppress UndefinedFunction */
        return array_map(
            static function (array $call) : RecordedCall {
                return RecordedCall::fromExtensionLoggedCallArray($call);
            },
            scoutapm_get_calls()
        );
    }

    private function extensionIsAvailable() : bool
    {
        return extension_loaded('scoutapm')
            && function_exists('scoutapm_get_calls');
    }
}
