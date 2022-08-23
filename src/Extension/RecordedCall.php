<?php

declare(strict_types=1);

namespace Scoutapm\Extension;

use Webmozart\Assert\Assert;

use function array_key_exists;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function preg_replace;
use function stripos;
use function strtoupper;

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
     * @psalm-param array{function:string, entered:float, exited: float, time_taken: float, argv: mixed[]} $extensionCall
     *
     * @return RecordedCall
     */
    public static function fromExtensionLoggedCallArray(array $extensionCall): self
    {
        Assert::keyExists($extensionCall, 'function');
        Assert::keyExists($extensionCall, 'entered');
        Assert::keyExists($extensionCall, 'exited');
        Assert::keyExists($extensionCall, 'time_taken');
        Assert::keyExists($extensionCall, 'argv');

        return new self(
            $extensionCall['function'],
            $extensionCall['time_taken'],
            $extensionCall['entered'],
            $extensionCall['exited'],
            $extensionCall['argv']
        );
    }

    public function functionName(): string
    {
        return $this->function;
    }

    public function timeTakenInSeconds(): float
    {
        return $this->timeTakenInSeconds;
    }

    public function timeEntered(): float
    {
        return $this->timeEntered;
    }

    public function timeExited(): float
    {
        return $this->timeExited;
    }

    /**
     * We should never return the full set of arguments, only specific arguments for specific functions. This is to
     * avoid potentially spilling personally identifiable information. Another reason to only return specific arguments
     * is to avoid sending loads of data unnecessarily.
     *
     * @return list<empty>|array{url: string, method: string}
     */
    public function filteredArguments(): array
    {
        if ($this->function === 'file_get_contents' || $this->function === 'curl_exec') {
            $method = 'GET';

            // file_get_contents was used with a stream context
            if (
                $this->function === 'file_get_contents'
                && array_key_exists(2, $this->arguments)
                && is_string($this->arguments[2])
            ) {
                /** @var mixed $fileGetContentsStreamContext */
                $fileGetContentsStreamContext = json_decode($this->arguments[2], true);
                if (
                    is_array($fileGetContentsStreamContext)
                    && array_key_exists('http', $fileGetContentsStreamContext)
                    && is_array($fileGetContentsStreamContext['http'])
                    && array_key_exists('method', $fileGetContentsStreamContext['http'])
                    && is_string($fileGetContentsStreamContext['http']['method'])
                    && ! empty($fileGetContentsStreamContext['http']['method'])
                ) {
                    $method = $fileGetContentsStreamContext['http']['method'];
                }
            }

            // curl_exec with CURLOPT_POST option was used with a truthy value
            if ($this->function === 'curl_exec' && array_key_exists(1, $this->arguments) && $this->arguments[1]) {
                $method = 'POST';
            }

            // curl_exec with CURLOPT_POST option was used with a truthy value
            if (
                $this->function === 'curl_exec'
                && array_key_exists(2, $this->arguments)
                && is_string($this->arguments[2])
                && ! empty($this->arguments[2])
            ) {
                $method = $this->arguments[2];
            }

            return [
                'url' => (string) $this->arguments[0],
                'method' => preg_replace('/[^A-Z]/', '', strtoupper($method)),
            ];
        }

        return [];
    }

    public function maybeHttpUrl(): ?string
    {
        if (! in_array($this->function, ['file_get_contents', 'curl_exec'], true)) {
            return null;
        }

        $arguments = $this->filteredArguments();

        if (! array_key_exists('url', $arguments)) {
            return null;
        }

        $url = $arguments['url'];

        if (stripos($url, 'http://') !== 0 && stripos($url, 'https://') !== 0) {
            return null;
        }

        return $url;
    }

    public function maybeHttpMethod(): ?string
    {
        if (! in_array($this->function, ['file_get_contents', 'curl_exec'], true)) {
            return null;
        }

        $arguments = $this->filteredArguments();

        if (! array_key_exists('method', $arguments)) {
            return null;
        }

        return $arguments['method'];
    }
}
