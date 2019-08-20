<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Config;

use PHPUnit\Framework\TestCase;
use Scoutapm\Agent;
use Scoutapm\Config\IgnoredEndpoints;

/** @covers \Scoutapm\Config\IgnoredEndpoints */
final class IgnoredEndpointsTest extends TestCase
{
    public function testIgnoresEndpoints() : void
    {
        $agent  = new Agent();
        $config = $agent->getConfig();
        $config->set('ignore', [
            '/health',
            '/status',
        ]);
        $ignoredEndpoints = new IgnoredEndpoints($agent);

        // Exact Match
        self::assertTrue($ignoredEndpoints->ignored('/health'));
        self::assertTrue($ignoredEndpoints->ignored('/status'));

        // Prefix Match
        self::assertTrue($ignoredEndpoints->ignored('/health/database'));
        self::assertTrue($ignoredEndpoints->ignored('/status/time'));

        // No Match
        self::assertFalse($ignoredEndpoints->ignored('/signup'));

        // Not-prefix doesn't Match
        self::assertFalse($ignoredEndpoints->ignored('/hero/1/health'));
    }

    public function testWorksWithNullIgnoreSetting() : void
    {
        // No Match
        self::assertFalse((new IgnoredEndpoints(new Agent()))->ignored('/signup'));
    }
}
