<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Config\Source;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;
use Scoutapm\Config\Source\DerivedSource;

/** @covers \Scoutapm\Config\Source\DerivedSource */
final class DerivedSourceTest extends TestCase
{
    /** @var Config */
    private $config;

    /** @var DerivedSource */
    private $derivedSource;

    public function setUp(): void
    {
        parent::setUp();

        $this->config = new Config();

        $this->derivedSource = new DerivedSource($this->config);
    }

    public function testHasKey(): void
    {
        self::assertTrue($this->derivedSource->hasKey(ConfigKey::CORE_AGENT_SOCKET_PATH));
        self::assertTrue($this->derivedSource->hasKey(ConfigKey::CORE_AGENT_FULL_NAME));
        self::assertTrue($this->derivedSource->hasKey(ConfigKey::CORE_AGENT_TRIPLE));
        self::assertFalse($this->derivedSource->hasKey('is_array'));
    }

    public function testGetReturnsNullWhenConfigKeyDoesNotExist(): void
    {
        self::assertNull($this->derivedSource->get('not an actual key'));
    }

    public function testCoreAgentFullNameIsDerivedCorrectly(): void
    {
        self::assertStringMatchesFormat(
            'scout_apm_core-v%d.%d.%d-%s-linux-musl',
            $this->derivedSource->get(ConfigKey::CORE_AGENT_FULL_NAME)
        );
    }

    public function testSocketPathIsDerivedCorrectly(): void
    {
        self::assertSame(
            'tcp://127.0.0.1:6590',
            $this->derivedSource->get(ConfigKey::CORE_AGENT_SOCKET_PATH)
        );
    }

    public function testMuslIsUsedForLibcVersion(): void
    {
        self::assertStringEndsWith('linux-musl', $this->derivedSource->get(ConfigKey::CORE_AGENT_TRIPLE));
    }
}
