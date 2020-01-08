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
    /** @var Config */
    private $config;

    /** @var DerivedSource */
    private $derivedSource;

    public function setUp() : void
    {
        parent::setUp();

        $this->config = new Config();

        $this->derivedSource = new DerivedSource($this->config, new LibcDetection('/' . uniqid('file_should_not_exist', true)));
    }

    public function testHasKey() : void
    {
        self::assertTrue($this->derivedSource->hasKey(ConfigKey::CORE_AGENT_SOCKET_PATH));
        self::assertTrue($this->derivedSource->hasKey(ConfigKey::CORE_AGENT_FULL_NAME));
        self::assertTrue($this->derivedSource->hasKey(ConfigKey::CORE_AGENT_TRIPLE));
        self::assertFalse($this->derivedSource->hasKey('is_array'));
    }

    public function testGetReturnsNullWhenConfigKeyDoesNotExist() : void
    {
        self::assertNull($this->derivedSource->get('not an actual key'));
    }

    public function testCoreAgentFullNameIsDerivedCorrectly() : void
    {
        self::assertStringMatchesFormat(
            'scout_apm_core-v%d.%d.%d-%s-linux-gnu',
            $this->derivedSource->get(ConfigKey::CORE_AGENT_FULL_NAME)
        );
    }

    public function testSocketPathIsDerivedCorrectly() : void
    {
        $this->config->set(ConfigKey::CORE_AGENT_FULL_NAME, '__core_agent_full_name__');

        self::assertSame(
            '/tmp/scout_apm_core/__core_agent_full_name__/scout-agent.sock',
            $this->derivedSource->get(ConfigKey::CORE_AGENT_SOCKET_PATH)
        );
    }

    public function testMuslIsDetectedWhenAlpineFileDetected() : void
    {
        $muslHintFilename = tempnam(sys_get_temp_dir(), 'scoutapm_musl_hint_file');

        $derivedSource = new DerivedSource(new Config(), new LibcDetection($muslHintFilename));

        self::assertStringEndsWith('linux-musl', $derivedSource->get(ConfigKey::CORE_AGENT_TRIPLE));

        unlink($muslHintFilename);
    }

    public function testGnuLibcIsDetectedWhenAlpineFileDoesNotExist() : void
    {
        self::assertStringEndsWith('linux-gnu', $this->derivedSource->get(ConfigKey::CORE_AGENT_TRIPLE));
    }
}
