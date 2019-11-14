<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Config\Source;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config\Source\DefaultSource;
use function array_keys;

/** @covers \Scoutapm\Config\Source\DefaultSource */
final class DefaultSourceTest extends TestCase
{
    public function testHasKey() : void
    {
        $defaults = new DefaultSource();
        self::assertTrue($defaults->hasKey('api_version'));
        self::assertFalse($defaults->hasKey('notAValue'));
    }

    public function testGet() : void
    {
        $defaults = new DefaultSource();
        self::assertSame('1.0', $defaults->get('api_version'));
    }

    public function testAsArrayContainsExpectedKeys() : void
    {
        self::assertEquals(
            [
                'api_version',
                'core_agent_dir',
                'core_agent_download',
                'core_agent_launch',
                'core_agent_version',
                'core_agent_download_url',
                'core_agent_permissions',
                'monitor',
                'ignore',
                'log_level',
            ],
            array_keys((new DefaultSource())->asArrayWithSecretsRemoved())
        );
    }
}
