<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\CoreAgent;

use PHPUnit\Framework\TestCase;
use Scoutapm\Agent;
use Scoutapm\CoreAgent\AutomaticDownloadAndLaunchManager;

/** @covers \Scoutapm\CoreAgent\AutomaticDownloadAndLaunchManager */
final class CoreAgentManagerTest extends TestCase
{
    public function testInitialize() : void
    {
        $cam = new AutomaticDownloadAndLaunchManager(Agent::fromDefaults());

        // Provided by the DefaultConfig
        self::assertNotNull($cam);
    }
}
