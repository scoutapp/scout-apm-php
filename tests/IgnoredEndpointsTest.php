<?php
namespace Scoutapm\Tests;

use \PHPUnit\Framework\TestCase;
use \Scoutapm\Agent;
use \Scoutapm\IgnoredEndpoints;

/**
 * Test Case for @see \Scoutapm\IgnoredEndpoints
 */
final class IgnoredEndpointsTest extends TestCase
{
    public function testIgnoresEndpoints()
    {
        $agent = new Agent();
        $config = $agent->getConfig();
        $config->set("ignore", [
            "/health",
            "/status",
        ]);
        $ignoredEndpoints = new IgnoredEndpoints($agent);

        // Exact Match
        $this->assertEquals(true, $ignoredEndpoints->ignored("/health"));
        $this->assertEquals(true, $ignoredEndpoints->ignored("/status"));

        // Prefix Match
        $this->assertEquals(true, $ignoredEndpoints->ignored("/health/database"));
        $this->assertEquals(true, $ignoredEndpoints->ignored("/status/time"));

        // No Match
        $this->assertEquals(false, $ignoredEndpoints->ignored("/signup"));

        // Not-prefix doesn't Match
        $this->assertEquals(false, $ignoredEndpoints->ignored("/hero/1/health"));
    }

    public function testWorksWithNullIgnoreSetting()
    {
        $agent = new Agent();
        $config = $agent->getConfig();
        $ignoredEndpoints = new IgnoredEndpoints($agent);

        // No Match
        $this->assertEquals(false, $ignoredEndpoints->ignored("/signup"));
    }
}
