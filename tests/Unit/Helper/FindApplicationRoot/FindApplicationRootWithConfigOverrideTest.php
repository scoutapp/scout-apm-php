<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Helper\FindApplicationRoot;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Scoutapm\Config;
use Scoutapm\Helper\FindApplicationRoot\FindApplicationRootWithConfigOverride;
use Scoutapm\Helper\LocateFileOrFolder\LocateFileOrFolder;
use Scoutapm\Helper\Superglobals\SuperglobalsArrays;

/** @covers \Scoutapm\Helper\FindApplicationRoot\FindApplicationRootWithConfigOverride */
final class FindApplicationRootWithConfigOverrideTest extends TestCase
{
    /** @var LocateFileOrFolder&MockObject */
    private $locateFileOrFolder;

    public function setUp(): void
    {
        parent::setUp();

        $this->locateFileOrFolder = $this->createMock(LocateFileOrFolder::class);
    }

    public function testConfigurationOverridesApplicationRoot(): void
    {
        $findApplicationRoot = new FindApplicationRootWithConfigOverride(
            $this->locateFileOrFolder,
            Config::fromArray([Config\ConfigKey::APPLICATION_ROOT => '/my/configured/app/root']),
            new SuperglobalsArrays([], [], [], ['DOCUMENT_ROOT' => '/my/document/root/path'])
        );

        self::assertSame('/my/configured/app/root', ($findApplicationRoot)());
    }

    public function testComposerJsonLocationCanBeUsedAsApplicationRoot(): void
    {
        $findApplicationRoot = new FindApplicationRootWithConfigOverride(
            $this->locateFileOrFolder,
            Config::fromArray([]),
            new SuperglobalsArrays([], [], [], ['DOCUMENT_ROOT' => '/my/document/root/path'])
        );

        $this->locateFileOrFolder
            ->expects(self::once())
            ->method('__invoke')
            ->with('composer.json')
            ->willReturn('/path/to/composer_json');

        self::assertSame('/path/to/composer_json', ($findApplicationRoot)());
    }

    public function testMissingDocumentRootInServerWillReturnEmptyString(): void
    {
        $findApplicationRoot = new FindApplicationRootWithConfigOverride(
            $this->locateFileOrFolder,
            Config::fromArray([]),
            new SuperglobalsArrays([], [], [], [])
        );
        self::assertSame('', ($findApplicationRoot)());
    }

    public function testDocumentRootIsReturned(): void
    {
        $findApplicationRoot = new FindApplicationRootWithConfigOverride(
            $this->locateFileOrFolder,
            Config::fromArray([]),
            new SuperglobalsArrays([], [], [], ['DOCUMENT_ROOT' => '/my/document/root/path'])
        );
        self::assertSame('/my/document/root/path', ($findApplicationRoot)());
    }
}
