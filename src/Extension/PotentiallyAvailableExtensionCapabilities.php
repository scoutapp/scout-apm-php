<?php

declare(strict_types=1);

namespace Scoutapm\Extension;

use Throwable;

use function array_map;
use function extension_loaded;
use function function_exists;
use function phpversion;
use function scoutapm_enable_instrumentation;
use function scoutapm_get_calls;

final class PotentiallyAvailableExtensionCapabilities implements ExtensionCapabilities
{
    public function __construct()
    {
        if (! $this->extensionIsAvailable()) {
            return;
        }

        // If the function doesn't exist, we're probably using an older `scoutapm` extension which doesn't need enabling
        if (! function_exists('scoutapm_enable_instrumentation')) {
            return;
        }

        scoutapm_enable_instrumentation(true);
    }

    /**
     * @return RecordedCall[]
     *
     * @psalm-return list<RecordedCall>
     */
    public function getCalls(): array
    {
        if (! $this->extensionIsAvailable()) {
            return [];
        }

        /** @psalm-suppress UndefinedFunction */
        return array_map(
            static function (array $call): RecordedCall {
                return RecordedCall::fromExtensionLoggedCallArray($call);
            },
            scoutapm_get_calls()
        );
    }

    public function clearRecordedCalls(): void
    {
        if (! $this->extensionIsAvailable()) {
            return;
        }

        /** @psalm-suppress UndefinedFunction */
        scoutapm_get_calls();
    }

    private function extensionIsAvailable(): bool
    {
        return extension_loaded('scoutapm')
            && function_exists('scoutapm_get_calls');
    }

    public function version(): ?Version
    {
        if (! $this->extensionIsAvailable()) {
            return null;
        }

        try {
            return Version::fromString(phpversion('scoutapm'));
        } catch (Throwable $anything) {
            return null;
        }
    }
}
