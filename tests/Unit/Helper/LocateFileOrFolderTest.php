<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Helper;

use PHPUnit\Framework\TestCase;
use Scoutapm\Helper\LocateFileOrFolder\LocateFileOrFolderUsingFilesystem;

use function realpath;

/** @covers \Scoutapm\Helper\LocateFileOrFolder\LocateFileOrFolderUsingFilesystem */
final class LocateFileOrFolderTest extends TestCase
{
    public function testInvokeFindsComposerPathCorrectly(): void
    {
        $composerLocation = (new LocateFileOrFolderUsingFilesystem())->__invoke('composer.json', 0);
        self::assertSame(
            realpath(__DIR__ . '/../../../'),
            $composerLocation
        );
    }

    public function testDefaultNumberOfLevelsSkipsComposerJson(): void
    {
        self::assertNull((new LocateFileOrFolderUsingFilesystem())->__invoke('composer.json'));
    }
}
