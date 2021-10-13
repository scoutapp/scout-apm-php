<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Helper;

use PHPUnit\Framework\TestCase;
use Scoutapm\Helper\FilterParameters;
use stdClass;

use function fopen;

/** @covers \Scoutapm\Helper\FilterParameters */
final class FilterParametersTest extends TestCase
{
    /**
     * @psalm-return array<string, array{inputParameters: array, parameterKeysToBeFiltered: list<string>, expectedFiltered: array}>
     */
    public function uriReportingConfigurationProvider(): array
    {
        return [
            'empty' => [
                'inputParameters' => [],
                'parameterKeysToBeFiltered' => [],
                'expectedFiltered' => [],
            ],
            'noFiltering' => [
                'inputParameters' => ['a' => 'a'],
                'parameterKeysToBeFiltered' => [],
                'expectedFiltered' => ['a' => 'a'],
            ],
            'allFiltered' => [
                'inputParameters' => ['a' => 'a'],
                'parameterKeysToBeFiltered' => ['a'],
                'expectedFiltered' => [],
            ],
            'someFiltered' => [
                'inputParameters' => ['a' => 'a', 'b' => 'b'],
                'parameterKeysToBeFiltered' => ['a'],
                'expectedFiltered' => ['b' => 'b'],
            ],
            'noneFiltered' => [
                'inputParameters' => ['a' => 'a', 'b' => 'b'],
                'parameterKeysToBeFiltered' => ['c', 'c'],
                'expectedFiltered' => ['a' => 'a', 'b' => 'b'],
            ],
        ];
    }

    /**
     * @param array<array-key, mixed> $inputParameters
     * @param list<string>            $parameterKeysToBeFiltered
     * @param array<array-key, mixed> $expectedFiltered
     *
     * @dataProvider uriReportingConfigurationProvider
     */
    public function testForUriReportingConfiguration(
        array $inputParameters,
        array $parameterKeysToBeFiltered,
        array $expectedFiltered
    ): void {
        self::assertSame(
            $expectedFiltered,
            FilterParameters::forUriReportingConfiguration(
                $parameterKeysToBeFiltered,
                $inputParameters
            )
        );
    }

    /**
     * @psalm-return array<string, array{inputParameters: array, parameterKeysToBeFiltered: list<string>, depth: int, expectedFiltered: array}>
     */
    public function flattenedUriReportingConfiguration(): array
    {
        return [
            'empty' => [
                'inputParameters' => [],
                'parameterKeysToBeFiltered' => [],
                'depth' => 1,
                'expectedFiltered' => [],
            ],
            'noFiltering' => [
                'inputParameters' => ['a' => 'a'],
                'parameterKeysToBeFiltered' => [],
                'depth' => 1,
                'expectedFiltered' => ['a' => 'a'],
            ],
            'allFiltered' => [
                'inputParameters' => ['a' => 'a'],
                'parameterKeysToBeFiltered' => ['a'],
                'depth' => 1,
                'expectedFiltered' => [],
            ],
            'someFiltered' => [
                'inputParameters' => ['a' => 'a', 'b' => 'b'],
                'parameterKeysToBeFiltered' => ['a'],
                'depth' => 1,
                'expectedFiltered' => ['b' => 'b'],
            ],
            'noneFiltered' => [
                'inputParameters' => ['a' => 'a', 'b' => 'b'],
                'parameterKeysToBeFiltered' => ['c', 'c'],
                'depth' => 1,
                'expectedFiltered' => ['a' => 'a', 'b' => 'b'],
            ],
            'flattenedArray' => [
                'inputParameters' => ['a' => ['a1' => 'a1'], 'b' => 'b'],
                'parameterKeysToBeFiltered' => [],
                'depth' => 1,
                'expectedFiltered' => ['a' => 'array', 'b' => 'b'],
            ],
            'flattenedDeeperArray' => [
                'inputParameters' => ['a' => ['a1' => ['a1-1' => 'a1-1']], 'b' => 'b'],
                'parameterKeysToBeFiltered' => [],
                'depth' => 2,
                'expectedFiltered' => ['a' => ['a1' => 'array'], 'b' => 'b'],
            ],
            'flattenedObject' => [
                'inputParameters' => ['a' => new stdClass(), 'b' => 'b'],
                'parameterKeysToBeFiltered' => [],
                'depth' => 1,
                'expectedFiltered' => ['a' => 'object(stdClass)', 'b' => 'b'],
            ],
            'integerConvertedToString' => [
                'inputParameters' => ['a' => 1, 'b' => 'b'],
                'parameterKeysToBeFiltered' => [],
                'depth' => 1,
                'expectedFiltered' => ['a' => '1', 'b' => 'b'],
            ],
            'resourceConvertedToString' => [
                'inputParameters' => ['a' => fopen(__FILE__, 'rb'), 'b' => 'b'],
                'parameterKeysToBeFiltered' => [],
                'depth' => 1,
                'expectedFiltered' => ['a' => 'resource', 'b' => 'b'],
            ],
        ];
    }

    /**
     * @param array<array-key, mixed> $inputParameters
     * @param list<string>            $parameterKeysToBeFiltered
     * @param array<array-key, mixed> $expectedFiltered
     *
     * @dataProvider flattenedUriReportingConfiguration
     */
    public function testFlattenedForUriReportingConfiguration(
        array $inputParameters,
        array $parameterKeysToBeFiltered,
        int $depth,
        array $expectedFiltered
    ): void {
        self::assertSame(
            $expectedFiltered,
            FilterParameters::flattenedForUriReportingConfiguration(
                $parameterKeysToBeFiltered,
                $inputParameters,
                $depth
            )
        );
    }
}
