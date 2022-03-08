<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Helper;

use PHPUnit\Framework\TestCase;
use Scoutapm\Helper\ComposerPackagesCheck;

/** @covers \Scoutapm\Helper\ComposerPackagesCheck */
final class ComposerPackagesCheckTest extends TestCase
{
    public function testPhpLibraryVersion(): void
    {
        self::assertStringStartsWith('dev-', ComposerPackagesCheck::phpLibraryVersion());
    }
}
