<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Config;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config\ConfigKey;

/** @covers \Scoutapm\Config\ConfigKey */
final class ConfigKeyTest extends TestCase
{
    public function testAllConfigurationKeysReturnsArrayOfKeys(): void
    {
        self::assertContainsOnly('string', ConfigKey::allConfigurationKeys());
    }

    public function testFilterSecretsFromConfigArray(): void
    {
        self::assertEquals(
            [
                ConfigKey::APPLICATION_KEY => '<redacted>',
                ConfigKey::APPLICATION_NAME => 'Just the App Name',
            ],
            ConfigKey::filterSecretsFromConfigArray([
                ConfigKey::APPLICATION_KEY => 'this is a secret',
                ConfigKey::APPLICATION_NAME => 'Just the App Name',
            ])
        );
    }
}
