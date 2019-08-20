<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\CoreAgent;

use PHPUnit\Framework\TestCase;
use Scoutapm\Agent;
use Scoutapm\CoreAgent\Manager;

/** @covers \Scoutapm\CoreAgent\Manager */
final class CoreAgentManagerTest extends TestCase
{
    public function testInitialize() : void
    {
        $agent = new Agent();
        $cam   = new Manager($agent);

        // Provided by the DefaultConfig
        self::assertNotNull($cam);
    }
}
