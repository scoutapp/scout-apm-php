<?php

declare(strict_types=1);

namespace Scoutapm\Extension;

use Webmozart\Assert\Assert;

final class RecordedCall
{
    /** @var string */
    private $function;

    /** @var float */
    private $timeTakenInMicroseconds;

    private function __construct(string $function, float $timeTakenInMicroseconds)
    {
        $this->function                = $function;
        $this->timeTakenInMicroseconds = $timeTakenInMicroseconds;
    }

    /**
     * @param string[]|float[]|array<string, (string|float)> $extensionCall
     *
     * @return RecordedCall
     */
    public static function fromExtensionLoggedCallArray(array $extensionCall) : self
    {
        Assert::keyExists($extensionCall, 'function');
        Assert::keyExists($extensionCall, 'entered');
        Assert::keyExists($extensionCall, 'exited');
        Assert::keyExists($extensionCall, 'time_taken');

        return new self(
            (string) $extensionCall['function'],
            (float) $extensionCall['time_taken']
        );
    }

    public function functionName() : string
    {
        return $this->function;
    }

    public function timeTakenInMicroseconds() : float
    {
        return $this->timeTakenInMicroseconds;
    }
}
