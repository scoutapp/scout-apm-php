<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests;

use PHPUnit\Framework\TestCase;
use Scoutapm\Agent;
use Scoutapm\IgnoredEndpoints;

/** @covers \Scoutapm\IgnoredEndpoints */
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
        self::assertEquals(true, $ignoredEndpoints->ignored('/health'));
        self::assertEquals(true, $ignoredEndpoints->ignored('/status'));

        // Prefix Match
        self::assertEquals(true, $ignoredEndpoints->ignored('/health/database'));
        self::assertEquals(true, $ignoredEndpoints->ignored('/status/time'));

        // No Match
        self::assertEquals(false, $ignoredEndpoints->ignored('/signup'));

        // Not-prefix doesn't Match
        self::assertEquals(false, $ignoredEndpoints->ignored('/hero/1/health'));
    }

    public function testWorksWithNullIgnoreSetting() : void
    {
        $agent            = new Agent();
        $config           = $agent->getConfig();
        $ignoredEndpoints = new IgnoredEndpoints($agent);

        // No Match
        self::assertEquals(false, $ignoredEndpoints->ignored('/signup'));
    }
}
