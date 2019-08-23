<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Extension;

use PHPUnit\Framework\TestCase;
use Scoutapm\Extension\PotentiallyAvailableExtensionCapabilities;
use Scoutapm\Extension\RecordedCall;

/** @covers \Scoutapm\Extension\PotentiallyAvailableExtensionCapabilities */
final class PotentiallyAvailableExtensionCapabilitiesTest extends TestCase
{
    public function testGetCallsReturnsEmptyArrayWhenExtensionNotAvailable() : void
    {
        if (extension_loaded('scoutapm')) {
            self::markTestSkipped('Test can only be run when extension is not available');
            return;
        }

        /** @noinspection UnusedFunctionResultInspection */
        file_get_contents(__FILE__);
        self::assertEquals([], (new PotentiallyAvailableExtensionCapabilities())->getCalls());
    }

    public function testGetCallsReturnsFileGetContentsCallWhenExtensionIsAvailable() : void
    {
        if (!extension_loaded('scoutapm')) {
            self::markTestSkipped('Test can only be run when extension is loaded');
        }

        // First call is to clear any existing logged calls from the extension so we are in a known state
        /** @noinspection UnusedFunctionResultInspection */
        (new PotentiallyAvailableExtensionCapabilities())->getCalls();

        /** @noinspection UnusedFunctionResultInspection */
        file_get_contents(__FILE__);

        $calls = (new PotentiallyAvailableExtensionCapabilities())->getCalls();

        self::assertCount(1, $calls);
        self::assertContainsOnlyInstancesOf(RecordedCall::class, $calls);

        $recordedCall = reset($calls);

        self::assertSame('file_get_contents', $recordedCall->functionName());
        self::assertGreaterThan(0, $recordedCall->timeTakenInMicroseconds());
    }
}
