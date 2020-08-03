<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Helper;

use PHPUnit\Framework\TestCase;
use Scoutapm\Helper\LocateFileOrFolder;
use function realpath;

/** @covers \Scoutapm\Helper\LocateFileOrFolder */
final class LocateFileOrFolderTest extends TestCase
{
    public function testInvokeFindsComposerPathCorrectly() : void
    {
        $composerLocation = (new LocateFileOrFolder())->__invoke('composer.json', 0);
        self::assertSame(
            realpath(__DIR__ . '/../../../'),
            $composerLocation
        );
    }
}
