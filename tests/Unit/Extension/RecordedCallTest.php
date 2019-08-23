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
        $timeTaken = random_int(1, 100) / 1000;

        $call = RecordedCall::fromExtensionLoggedCallArray([
            'function' => $function,
            'entered' => microtime(true),
            'exited' => microtime(true),
            'time_taken' => $timeTaken,
        ]);

        self::assertSame($timeTaken, $call->timeTakenInSeconds());
        self::assertSame($function, $call->functionName());
    }
}
