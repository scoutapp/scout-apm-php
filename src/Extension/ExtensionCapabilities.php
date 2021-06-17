<?php

declare(strict_types=1);

namespace Scoutapm\Extension;

interface ExtensionCapabilities
{
    /** @return RecordedCall[]|array<int, RecordedCall> */
    public function getCalls(): array;

    public function clearRecordedCalls(): void;

    public function version(): ?Version;

    public function enable(): void;
}
