<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\CoreAgent;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Scoutapm\Config;
use Scoutapm\CoreAgent\AutomaticDownloadAndLaunchManager;
use Scoutapm\CoreAgent\Downloader;

/** @covers \Scoutapm\CoreAgent\AutomaticDownloadAndLaunchManager */
final class CoreAgentManagerTest extends TestCase
{
    public function testInitialize() : void
    {
        $cam = new AutomaticDownloadAndLaunchManager(
            new Config(),
            $this->createMock(LoggerInterface::class),
            $this->createMock(Downloader::class)
        );

        // Provided by the DefaultConfig
        self::assertNotNull($cam);
    }
}
