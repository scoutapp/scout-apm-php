<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Extension;

use PHPUnit\Framework\TestCase;
use Scoutapm\Extension\PotentiallyAvailableExtensionCapabilities;

use function extension_loaded;
use function file_get_contents;
use function phpversion;
use function reset;

/** @covers \Scoutapm\Extension\PotentiallyAvailableExtensionCapabilities */
final class PotentiallyAvailableExtensionCapabilitiesTest extends TestCase
{
    /** @var PotentiallyAvailableExtensionCapabilities */
    private $capabilities;

    public function setUp(): void
    {
        parent::setUp();

        $this->capabilities = new PotentiallyAvailableExtensionCapabilities();

        // First call is to clear any existing logged calls from the extension so we are in a known state
        $this->capabilities->clearRecordedCalls();
    }

    public function testGetCallsReturnsEmptyArrayWhenExtensionNotAvailable(): void
    {
        if (extension_loaded('scoutapm')) {
            self::markTestSkipped('Test can only be run when extension is not available');
        }

        /** @noinspection UnusedFunctionResultInspection */
        file_get_contents(__FILE__);
        self::assertEquals([], $this->capabilities->getCalls());
    }

    public function testGetCallsReturnsFileGetContentsCallWhenExtensionIsAvailable(): void
    {
        if (! extension_loaded('scoutapm')) {
            self::markTestSkipped('Test can only be run when extension is loaded');
        }

        /** @noinspection UnusedFunctionResultInspection */
        file_get_contents(__FILE__);

        $calls = $this->capabilities->getCalls();

        self::assertCount(1, $calls);

        $recordedCall = reset($calls);

        self::assertSame('file_get_contents', $recordedCall->functionName());
        self::assertGreaterThan(0, $recordedCall->timeTakenInSeconds());
    }

    public function testRecordedCallsAreClearedWhenExtensionIsAvailable(): void
    {
        if (! extension_loaded('scoutapm')) {
            self::markTestSkipped('Test can only be run when extension is loaded');
        }

        /** @noinspection UnusedFunctionResultInspection */
        file_get_contents(__FILE__);

        $this->capabilities->clearRecordedCalls();

        self::assertCount(0, $this->capabilities->getCalls());

        /** @noinspection UnusedFunctionResultInspection */
        file_get_contents(__FILE__);

        $this->capabilities->clearRecordedCalls();

        self::assertCount(0, $this->capabilities->getCalls());
    }

    public function testVersionIsReturnedWhenAvailable(): void
    {
        if (! extension_loaded('scoutapm')) {
            self::markTestSkipped('Test can only be run when extension is loaded');
        }

        $version = $this->capabilities->version();

        self::assertNotNull($version);
        self::assertSame(phpversion('scoutapm'), $version->toString());
    }

    public function testVersionReturnsNullWhenExtensionNotLoaded(): void
    {
        if (extension_loaded('scoutapm')) {
            self::markTestSkipped('Test can only be run when extension is not available');
        }

        self::assertNull($this->capabilities->version());
    }
}
