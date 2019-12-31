<?php

declare(strict_types=1);

namespace Scoutapm\IntegrationTests;

use PHPUnit\Framework\Assert;
use function array_keys;
use function extension_loaded;
use function implode;
use function is_callable;
use function is_string;
use function sprintf;
use function var_export;

abstract class TestHelper
{
    public static function scoutApmExtensionAvailable() : bool
    {
        return extension_loaded('scoutapm');
    }

    /**
     * @param string[]|callable[]|array<string, (string|callable)>        $keysAndValuesToExpect
     * @param mixed[][]|array<string, array<string, (string|null|array)>> $actualCommand
     */
    public static function assertUnserializedCommandContainsPayload(
        string $expectedCommand,
        array $keysAndValuesToExpect,
        array $actualCommand,
        ?string $identifierKeyToReturn
    ) : ?string {
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
}
