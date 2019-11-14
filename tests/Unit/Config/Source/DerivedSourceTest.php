<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Config\Source;

use PHPUnit\Framework\TestCase;
use Scoutapm\Config;
use Scoutapm\Config\Source\DerivedSource;
use function array_keys;

/** @covers \Scoutapm\Config\Source\DerivedSource */
final class DerivedSourceTest extends TestCase
{
    public function testHasKey() : void
    {
        $derived = new DerivedSource(new Config());

        self::assertTrue($derived->hasKey('testing'));
        self::assertFalse($derived->hasKey('is_array'));
    }

    public function testGet() : void
    {
        $derived = new DerivedSource(new Config());

        self::assertSame('derived api version: 1.0', $derived->get('testing'));
    }

    public function testAsArrayContainsExpectedKeys() : void
    {
        self::assertEquals(
            [
                'core_agent_socket_path',
                'core_agent_full_name',
                'core_agent_triple',
                'testing',
            ],
            array_keys((new DerivedSource(new Config()))->asArrayWithSecretsRemoved())
        );
    }
}
