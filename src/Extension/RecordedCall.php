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

    /** @var float */
    private $timeEntered;

    /** @var float */
    private $timeExited;

    /** @var mixed[] */
    private $arguments;

    /** @param mixed[] $arguments */
    private function __construct(
        string $function,
        float $timeTakenInSeconds,
        float $timeEntered,
        float $timeExited,
        array $arguments
    ) {
        $this->function           = $function;
        $this->timeTakenInSeconds = $timeTakenInSeconds;
        $this->timeEntered        = $timeEntered;
        $this->timeExited         = $timeExited;
        $this->arguments          = $arguments;
    }

    /**
     * @param string[]|float[]|array<string, (string|float|mixed[])> $extensionCall
     *
     * @return RecordedCall
     *
     * @psalm-param array{function:string, entered:float, exited: float, time_taken: float, argv: mixed[]} $extensionCall
     */
    public static function fromExtensionLoggedCallArray(array $extensionCall) : self
    {
        Assert::keyExists($extensionCall, 'function');
        Assert::keyExists($extensionCall, 'entered');
        Assert::keyExists($extensionCall, 'exited');
        Assert::keyExists($extensionCall, 'time_taken');
        Assert::keyExists($extensionCall, 'argv');

        return new self(
            (string) $extensionCall['function'],
            (float) $extensionCall['time_taken'],
            (float) $extensionCall['entered'],
            (float) $extensionCall['exited'],
            $extensionCall['argv']
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

    public function timeEntered() : float
    {
        return $this->timeEntered;
    }

    public function timeExited() : float
    {
        return $this->timeExited;
    }

    /**
     * We should never return the full set of arguments, only specific arguments for specific functions. This is to
     * avoid potentially spilling personally identifiable information.
     *
     * @return mixed[]
     */
    public function filteredArguments() : array
    {
        if ($this->function === 'file_get_contents') {
            return [
                'url' => (string) $this->arguments[0],
            ];
        }

        return [];
    }
}
