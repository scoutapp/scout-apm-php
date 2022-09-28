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
     * @psalm-return array<int, array{testVersion: string, isOlderThan: string, expectedResult: bool}>
     */
    public function olderThanVersionProvider(): array
    {
        return [
            ['testVersion' => '1.0.0', 'isOlderThan' => '2.0.0', 'expectedResult' => true],
            ['testVersion' => '1.1.0', 'isOlderThan' => '1.2.0', 'expectedResult' => true],
            ['testVersion' => '1.1.1', 'isOlderThan' => '1.1.2', 'expectedResult' => true],
            ['testVersion' => '2.0.0', 'isOlderThan' => '1.0.0', 'expectedResult' => false],
            ['testVersion' => '1.2.0', 'isOlderThan' => '1.1.0', 'expectedResult' => false],
            ['testVersion' => '1.1.2', 'isOlderThan' => '1.1.1', 'expectedResult' => false],
            ['testVersion' => '1.0.0', 'isOlderThan' => '1.0.0', 'expectedResult' => false],
            ['testVersion' => '1.1.0', 'isOlderThan' => '1.1.0', 'expectedResult' => false],
            ['testVersion' => '1.1.1', 'isOlderThan' => '1.1.1', 'expectedResult' => false],
            ['testVersion' => '1.3.0', 'isOlderThan' => '0.0.1', 'expectedResult' => false],
            ['testVersion' => '2.0.0', 'isOlderThan' => '1.0.2', 'expectedResult' => false],
            ['testVersion' => '2.0.0', 'isOlderThan' => '1.2.0', 'expectedResult' => false],
        ];
    }

    /** @dataProvider olderThanVersionProvider */
    public function testOlderThan(string $testVersion, string $olderThan, bool $expectedResult): void
    {
        self::assertSame(
            $expectedResult,
            Version::fromString($testVersion)
                ->isOlderThan(Version::fromString($olderThan))
        );
    }

    public function testConvertsToString(): void
    {
        self::assertSame('1.2.3', Version::fromString('1.2.3')->toString());
    }
}
