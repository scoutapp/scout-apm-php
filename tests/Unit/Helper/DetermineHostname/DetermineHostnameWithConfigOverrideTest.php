<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Helper\DetermineHostname;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config;
use Scoutapm\Helper\DetermineHostname\DetermineHostnameWithConfigOverride;

use function gethostname;

/** @covers \Scoutapm\Helper\DetermineHostname\DetermineHostnameWithConfigOverride */
final class DetermineHostnameWithConfigOverrideTest extends TestCase
{
    public function testHostnameWhenConfiguredExplicitly(): void
    {
        self::assertSame('www.myspace.com', (new DetermineHostnameWithConfigOverride(Config::fromArray([Config\ConfigKey::HOSTNAME => 'www.myspace.com'])))());
    }

    public function testHostnameUsesSystemHostnameWhenNoConfiguration(): void
    {
        self::assertSame(gethostname(), (new DetermineHostnameWithConfigOverride(Config::fromArray([])))());
    }
}
