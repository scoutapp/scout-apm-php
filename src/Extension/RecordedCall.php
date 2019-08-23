<?php

declare(strict_types=1);

namespace Scoutapm\Extension;

use Webmozart\Assert\Assert;

final class RecordedCall
{
    /** @var string */
    private $function;

    /** @var float */
    private $timeTakenInSeconds;

    private function __construct(string $function, float $timeTakenInSeconds)
    {
        $this->function                = $function;
        $this->timeTakenInSeconds = $timeTakenInSeconds;
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

    public function timeTakenInSeconds() : float
    {
        return $this->timeTakenInSeconds;
    }
}
