<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Extension;

use Exception;
use PHPUnit\Framework\TestCase;
use Scoutapm\Extension\RecordedCall;
use function microtime;
use function random_int;
use function uniqid;

/** @covers \Scoutapm\Extension\RecordedCall */
final class RecordedCallTest extends TestCase
{
    /** @throws Exception */
    public function testFromExtensionLoggedCallArray() : void
    {
        $function  = uniqid('MyClass\Foo::method', true);
        $entered   = microtime(true) + random_int(1, 5);
        $exited    = microtime(true) + random_int(6, 10);
        $timeTaken = $exited - $entered;

        $call = RecordedCall::fromExtensionLoggedCallArray([
            'function' => $function,
            'entered' => $entered,
            'exited' => $exited,
            'time_taken' => $timeTaken,
            'argv' => [],
        ]);

        self::assertSame($entered, $call->timeEntered());
        self::assertSame($exited, $call->timeExited());
        self::assertSame($timeTaken, $call->timeTakenInSeconds());
        self::assertSame($function, $call->functionName());
        self::assertSame([], $call->filteredArguments());
    }

    public function filteredArgumentsDataProvider() : array
    {
        return [
            'file_get_contents' => [
                'recordedFunctionName' => 'file_get_contents',
                'expectedFilteredArguments' => ['url' => 'a'],
            ],
            'password_hash' => [
                'recordedFunctionName' => 'password_hashj',
                'expectedFilteredArguments' => [],
            ],
        ];
    }

    /**
     * @param mixed[] $expectedFilteredArguments
     * @dataProvider filteredArgumentsDataProvider
     * @throws \Exception
     */
    public function testOnlyFilteredArgumentsAreReturned(
        string $recordedFunctionName,
        array $expectedFilteredArguments
    ) : void {
        $entered   = microtime(true) + random_int(1, 5);
        $exited    = microtime(true) + random_int(6, 10);

        self::assertEquals(
            $expectedFilteredArguments,
            RecordedCall::fromExtensionLoggedCallArray([
                'function' => $recordedFunctionName,
                'entered' => $entered,
                'exited' => $exited,
                'time_taken' => $exited - $entered,
                'argv' => ['a', 'b', 'c', 'd', 'e', 'f'],
            ])->filteredArguments()
        );
    }
}
