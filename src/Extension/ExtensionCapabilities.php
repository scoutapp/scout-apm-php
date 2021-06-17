<?php

declare(strict_types=1);

namespace Scoutapm\Extension;

use function class_alias;

interface ExtensionCapabilities
{
    /** @return RecordedCall[]|array<int, RecordedCall> */
    public function getCalls(): array;

    public function clearRecordedCalls(): void;

    public function version(): ?Version;
}

class_alias(ExtensionCapabilities::class, ExtentionCapabilities::class);
