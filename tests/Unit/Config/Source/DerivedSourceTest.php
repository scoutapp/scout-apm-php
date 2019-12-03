<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Config\Source;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;
use Scoutapm\Config\Source\DerivedSource;
use Scoutapm\Helper\LibcDetection;
use function sys_get_temp_dir;
use function tempnam;
use function uniqid;
use function unlink;

/** @covers \Scoutapm\Config\Source\DerivedSource */
final class DerivedSourceTest extends TestCase
{
    public function testHasKey() : void
    {
        $derived = new DerivedSource(new Config(), new LibcDetection());

        self::assertTrue($derived->hasKey('testing'));
        self::assertFalse($derived->hasKey('is_array'));
    }

    public function testGet() : void
    {
        $derived = new DerivedSource(new Config(), new LibcDetection());

        self::assertSame('derived api version: 1.0', $derived->get('testing'));
    }

    public function testMuslIsDetectedWhenAlpineFileDetected() : void
    {
        $muslHintFilename = tempnam(sys_get_temp_dir(), 'scoutapm_musl_hint_file');

        $config = new DerivedSource(new Config(), new LibcDetection($muslHintFilename));

        self::assertStringEndsWith('linux-musl', $config->get(ConfigKey::CORE_AGENT_TRIPLE));

        unlink($muslHintFilename);
    }

    public function testGnuLibcIsDetectedWhenAlpineFileDoesNotExist() : void
    {
        $config = new DerivedSource(new Config(), new LibcDetection('/' . uniqid('file_should_not_exist', true)));

        self::assertStringEndsWith('linux-gnu', $config->get(ConfigKey::CORE_AGENT_TRIPLE));
    }
}
