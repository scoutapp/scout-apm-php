<?php

declare(strict_types=1);

namespace Scoutapm\Extension;

final class DoNotInvokeAnyExtensionCapabilities implements ExtensionCapabilities
{
    /** @return array<empty, empty> */
    public function getCalls(): array
    {
        // Intentionally, there are no calls
        return [];
    }

    public function clearRecordedCalls(): void
    {
        // Intentially no-op, don't invoke the extension
    }

    public function version(): ?Version
    {
        // Extension is ignored/doesn't exist, so no version to return
        return null;
    }
}
