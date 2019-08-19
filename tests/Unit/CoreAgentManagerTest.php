<?php
namespace Scoutapm\UnitTests;

use \PHPUnit\Framework\TestCase;
use \Scoutapm\Agent;
use \Scoutapm\CoreAgentManager;

/**
 * Test Case for @see \Scoutapm\CoreAgentManager
 */
final class CoreAgentManagerTest extends TestCase
{
    public function testInitialize()
    {
        $agent = new Agent();
        $cam = new CoreAgentManager($agent);

        // Provided by the DefaultConfig
        $this->assertNotNull($cam);
    }
}
