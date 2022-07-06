<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Extension;

use PHPUnit\Framework\TestCase;
use Scoutapm\Extension\DoNotInvokeAnyExtensionCapabilities;

use function file_get_contents;

/** @covers \Scoutapm\Extension\DoNotInvokeAnyExtensionCapabilities */
final class DoNotInvokeAnyExtensionCapabilitiesTest extends TestCase
{
    public function testClearRecordedCalls(): void
    {
        $capabilities = new DoNotInvokeAnyExtensionCapabilities();
        self::assertEquals([], $capabilities->getCalls());
        $capabilities->clearRecordedCalls();
        self::assertEquals([], $capabilities->getCalls());
    }

    public function testGetCalls(): void
    {
        file_get_contents(__FILE__);
        self::assertEquals([], (new DoNotInvokeAnyExtensionCapabilities())->getCalls());
    }

    public function testVersion(): void
    {
        self::assertNull((new DoNotInvokeAnyExtensionCapabilities())->version());
    }
}
