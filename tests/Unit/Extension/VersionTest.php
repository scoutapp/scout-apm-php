<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Extension;

use PHPUnit\Framework\TestCase;
use Scoutapm\Extension\Version;

/** @covers \Scoutapm\Extension\Version */
final class VersionTest extends TestCase
{
    /**
     * @return string[][]|bool[][]
     *
     * @psalm-return array<int, array{testVersion: string, olderThan: string, expectedResult: bool}>
     */
    public function olderThanVersionProvider() : array
    {
        return [
            ['testVersion' => '1.0.0', 'olderThan' => '2.0.0', 'expectedResult' => true],
            ['testVersion' => '1.1.0', 'olderThan' => '1.2.0', 'expectedResult' => true],
            ['testVersion' => '1.1.1', 'olderThan' => '1.1.2', 'expectedResult' => true],
            ['testVersion' => '2.0.0', 'olderThan' => '1.0.0', 'expectedResult' => false],
            ['testVersion' => '1.2.0', 'olderThan' => '1.1.0', 'expectedResult' => false],
            ['testVersion' => '1.1.2', 'olderThan' => '1.1.1', 'expectedResult' => false],
            ['testVersion' => '1.0.0', 'olderThan' => '1.0.0', 'expectedResult' => false],
            ['testVersion' => '1.1.0', 'olderThan' => '1.1.0', 'expectedResult' => false],
            ['testVersion' => '1.1.1', 'olderThan' => '1.1.1', 'expectedResult' => false],
        ];
    }

    /**
     * @dataProvider olderThanVersionProvider
     */
    public function testOlderThan(string $testVersion, string $olderThan, bool $expectedResult) : void
    {
        self::assertSame(
            $expectedResult,
            Version::fromString($testVersion)
                ->olderThan(Version::fromString($olderThan))
        );
    }

    public function testConvertsToString() : void
    {
        self::assertSame('1.2.3', Version::fromString('1.2.3')->toString());
    }
}
