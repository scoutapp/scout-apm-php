<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Helper;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Scoutapm\Config;
use Scoutapm\Helper\FindApplicationRoot;
use Scoutapm\Helper\LocateFileOrFolder;

/** @covers \Scoutapm\Helper\FindApplicationRoot */
final class FindApplicationRootTest extends TestCase
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
        $findApplicationRoot = new FindApplicationRoot($this->locateFileOrFolder, Config::fromArray([Config\ConfigKey::APPLICATION_ROOT => '/my/configured/app/root']));

        self::assertSame('/my/configured/app/root', ($findApplicationRoot)());
    }

    public function testComposerJsonLocationCanBeUsedAsApplicationRoot(): void
    {
        $findApplicationRoot = new FindApplicationRoot($this->locateFileOrFolder, Config::fromArray([]));

        $this->locateFileOrFolder
            ->expects(self::once())
            ->method('__invoke')
            ->with('composer.json')
            ->willReturn('/path/to/composer_json');

        self::assertSame('/path/to/composer_json', ($findApplicationRoot)());
    }

    public function testMissingDocumentRootInServerWillReturnEmptyString(): void
    {
        unset($_SERVER['DOCUMENT_ROOT']);
        $findApplicationRoot = new FindApplicationRoot($this->locateFileOrFolder, Config::fromArray([]));
        self::assertSame('', ($findApplicationRoot)());
    }

    public function testDocumentRootIsReturned(): void
    {
        $_SERVER['DOCUMENT_ROOT'] = '/my/document/root/path';
        $findApplicationRoot      = new FindApplicationRoot($this->locateFileOrFolder, Config::fromArray([]));
        self::assertSame('/my/document/root/path', ($findApplicationRoot)());
    }
}
