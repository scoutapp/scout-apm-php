<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Helper;

use PHPUnit\Framework\TestCase;

/** @covers \Scoutapm\Helper\DetermineHostname */
final class DetermineHostnameTest extends TestCase
{
    public function testHostnameWhenConfiguredExplicitly(): void
    {
        self::markTestIncomplete(__METHOD__); // @todo needs tests
    }

    public function testHostnameUsesSystemHostnameWhenNoConfiguration(): void
    {
        self::markTestIncomplete(__METHOD__); // @todo needs tests
    }
}
