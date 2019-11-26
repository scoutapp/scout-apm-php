<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Helper;

use PHPUnit\Framework\TestCase;
use Scoutapm\Helper\LibcDetection;
use function file_exists;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/** @covers \Scoutapm\Helper\LibcDetection */
final class LibcDetectionTest extends TestCase
{
    /** @var string */
    private $filename;

    /** @var LibcDetection */
    private $libcDetection;

    public function setUp() : void
    {
        parent::setUp();

        $this->filename = tempnam(sys_get_temp_dir(), 'scoutapm_musl_hint_file');
        self::assertFileExists($this->filename);

        $this->libcDetection = new LibcDetection($this->filename);
    }

    public function tearDown() : void
    {
        parent::tearDown();

        if (! file_exists($this->filename)) {
            return;
        }

        unlink($this->filename);
    }

    public function testDetectionOfMuslWhenHintFileExists() : void
    {
        self::assertSame('musl', $this->libcDetection->detect());
    }

    public function testDetectionOfGnuWhenHintFileDoesNotExist() : void
    {
        unlink($this->filename);
        self::assertSame('gnu', $this->libcDetection->detect());
    }
}
