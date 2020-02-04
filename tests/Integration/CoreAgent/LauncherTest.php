<?php

declare(strict_types=1);

namespace Scoutapm\IntegrationTests\CoreAgent;

use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use Scoutapm\CoreAgent\Launcher;

/** @covers \Scoutapm\CoreAgent\Launcher */
final class LauncherTest extends TestCase
{
    public function testLaunchingCoreAgentWithInvalidGlibcIsCaught() : void
    {
        $logger = new TestLogger();

        $launcher = new Launcher(
            $logger,
            'socket-path.sock',
            null,
            null,
            null
        );

        self::assertFalse($launcher->launch(__DIR__ . '/emulated-core-agent-glibc-error.sh'));
        $logger->hasDebugThatContains('core-agent currently needs at least glibc 2.18');
    }

    public function testLaunchCoreAgentWithNonZeroExitCodeIsCaught() : void
    {
        $logger = new TestLogger();

        $launcher = new Launcher(
            $logger,
            'socket-path.sock',
            null,
            null,
            null
        );

        self::assertFalse($launcher->launch(__DIR__ . '/emulated-unknown-error.sh'));
        $logger->hasDebugThatContains('core-agent exited with non-zero status. Output: Something bad went wrong');
    }

    public function testCoreAgentCanBeLaunched() : void
    {
        $logger = new TestLogger();

        $launcher = new Launcher(
            $logger,
            'socket-path.sock',
            null,
            null,
            null
        );

        self::assertTrue($launcher->launch(__DIR__ . '/emulated-happy-path.sh'));
    }
}
