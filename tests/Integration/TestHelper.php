<?php

declare(strict_types=1);

namespace Scoutapm\IntegrationTests;

use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\Assert;
use Scoutapm\Helper\Timer;

use function array_key_exists;
use function array_keys;
use function extension_loaded;
use function implode;
use function is_array;
use function is_callable;
use function is_string;
use function next;
use function sprintf;
use function var_export;

abstract class TestHelper
{
    public static function scoutApmExtensionAvailable(): bool
    {
        return extension_loaded('scoutapm');
    }

    /**
     * @param list<array<string, array<string, mixed>>> $commands
     *
     * @return array<string, array<string, mixed>>
     */
    public static function skipBacktraceTagIfNext(array &$commands): array
    {
        $nextCommand = next($commands);
        if (
            array_key_exists('TagSpan', $nextCommand)
            && is_array($nextCommand['TagSpan'])
            && $nextCommand['TagSpan']['tag'] === 'stack'
        ) {
            // In this case, the request was slow (can happen occasionally), so the stack trace was added.
            // We're not interested in stack traces in this test, so just skip it.
            $nextCommand = next($commands);
        }

        return $nextCommand;
    }

    /**
     * @param string[]|callable[]|array<string, mixed>      $keysAndValuesToExpect
     * @param mixed[][]|array<string, array<string, mixed>> $actualCommand
     */
    public static function assertUnserializedCommandContainsPayload(
        string $expectedCommand,
        array $keysAndValuesToExpect,
        array $actualCommand,
        ?string $identifierKeyToReturn
    ): ?string {
        Assert::assertArrayHasKey(
            $expectedCommand,
            $actualCommand,
            sprintf('Expected %s command, got %s', $expectedCommand, implode(',', array_keys($actualCommand)))
        );
        $commandPayload = $actualCommand[$expectedCommand];

        foreach ($keysAndValuesToExpect as $expectedKey => $expectedValue) {
            Assert::assertArrayHasKey(
                $expectedKey,
                $commandPayload,
                sprintf(
                    'Expected %s command to have %s key, contained: %s',
                    $expectedCommand,
                    $expectedKey,
                    implode(',', array_keys($commandPayload))
                )
            );

            if (! is_string($expectedValue) && is_callable($expectedValue)) {
                Assert::assertTrue(
                    $expectedValue($commandPayload[$expectedKey]),
                    sprintf(
                        'Callable for %s command %s did not return true - value was %s',
                        $expectedCommand,
                        $expectedKey,
                        var_export($commandPayload[$expectedKey], true)
                    )
                );
                continue;
            }

            Assert::assertSame(
                $expectedValue,
                $commandPayload[$expectedKey],
                sprintf(
                    'Value for %s command %s was expected to be %s, was %s',
                    $expectedCommand,
                    $expectedKey,
                    var_export($expectedValue, true),
                    var_export($commandPayload[$expectedKey], true)
                )
            );
        }

        if ($identifierKeyToReturn === null) {
            return null;
        }

        return $commandPayload[$identifierKeyToReturn];
    }

    /** @throws Exception */
    public static function assertValidTimestamp(?string $timestamp): bool
    {
        Assert::assertNotNull($timestamp, 'Expected a non-null timestamp, but the timestamp was null');
        Assert::assertSame($timestamp, (new DateTimeImmutable($timestamp))->format(Timer::FORMAT_FOR_CORE_AGENT));

        return true;
    }

    public static function assertValidMemoryUsage(?float $memoryUsageInMb): bool
    {
        Assert::assertIsFloat($memoryUsageInMb, 'Expected an float memory usage, but was it was null');
        Assert::assertGreaterThan(0, $memoryUsageInMb, 'Memory usage should be greater than zero');

        return true;
    }
}
