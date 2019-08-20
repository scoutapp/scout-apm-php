<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests;

use PHPUnit\Framework\TestCase;
use Scoutapm\Agent;
use Scoutapm\CoreAgentManager;

/** @covers \Scoutapm\CoreAgentManager*/
final class CoreAgentManagerTest extends TestCase
{
    public function testInitialize() : void
    {
        $agent = new Agent();
        $cam   = new CoreAgentManager($agent);

        // Provided by the DefaultConfig
        self::assertNotNull($cam);
    }
}
