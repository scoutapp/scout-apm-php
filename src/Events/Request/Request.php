<?php

declare(strict_types=1);

namespace Scoutapm\Events\Request;

use DateInterval;
use Exception;
use Scoutapm\Connector\Command;
use Scoutapm\Connector\CommandWithChildren;
use Scoutapm\Events\Span\Span;
use Scoutapm\Events\Tag\Tag;
use Scoutapm\Events\Tag\TagRequest;
use Scoutapm\Helper\FetchRequestHeaders;
use Scoutapm\Helper\MemoryUsage;
use Scoutapm\Helper\RecursivelyCountSpans;
use Scoutapm\Helper\Timer;
use function array_key_exists;
use function array_map;
use function is_string;
use function microtime;
use function strpos;
use function substr;

/** @internal */
class Request implements CommandWithChildren
{
    /** @var Timer */
    private $timer;
    /** @var Command[]|array<int, Command> */
    private $children = [];
    /** @var CommandWithChildren */
    private $currentCommand;
    /** @var RequestId */
    private $id;
    /** @var MemoryUsage */
    private $startMemory;
    /** @var string|null */
    private $requestUriOverride;

    /** @throws Exception */
    public function __construct(?float $override = null)
    {
        $this->id = RequestId::new();

        $this->timer       = new Timer($override);
        $this->startMemory = MemoryUsage::record();

        $this->currentCommand = $this;
    }

    public function cleanUp() : void
    {
        array_map(
            static function (Command $command) : void {
                $command->cleanUp();
            },
            $this->children
        );
        unset($this->timer, $this->children, $this->currentCommand, $this->id, $this->startMemory, $this->requestUriOverride);
    }

    public function overrideRequestUri(string $newRequestUri) : void
    {
        $this->requestUriOverride = $newRequestUri;
    }

    private function determineRequestPathFromServerGlobal() : string
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? null;

        if (is_string($requestUri)) {
            return $requestUri;
        }

        $origPathInfo = $_SERVER['ORIG_PATH_INFO'] ?? null;

        if (is_string($origPathInfo)) {
            return $origPathInfo;
        }

        return '/';
    }

    /**
     * Convert an ambiguous float timestamp that could be in nanoseconds, microseconds, milliseconds, or seconds to
     * nanoseconds. Return 0.0 for values in the more than 10 years ago.
     *
     * @throws Exception
     */
    private function convertAmbiguousTimestampToSeconds(float $timestamp, float $currentTimestamp) : float
    {
        $tenYearsAgo = Timer::utcDateTimeFromFloatTimestamp($currentTimestamp)
            ->sub(new DateInterval('P10Y'));

        $cutoffTimestamp = (float) $tenYearsAgo
            ->setDate((int) $tenYearsAgo->format('Y'), 1, 1)
            ->format('U.u');

        if ($timestamp > ($cutoffTimestamp * 1000000000.0)) {
            return $timestamp / 1000000000;
        }

        if ($timestamp > ($cutoffTimestamp * 1000000.0)) {
            return $timestamp / 1000000;
        }

        if ($timestamp > ($cutoffTimestamp * 1000.0)) {
            return $timestamp / 1000.0;
        }

        if ($timestamp > $cutoffTimestamp) {
            return $timestamp;
        }

        return 0.0;
    }

    /** @throws Exception */
    private function tagRequestIfRequestQueueTimeHeaderExists(float $currentTimeInSeconds) : void
    {
        $headers = FetchRequestHeaders::fromServerGlobal();

        foreach (['X-Queue-Start', 'X-Request-Start'] as $headerToCheck) {
            if (! array_key_exists($headerToCheck, $headers)) {
                continue;
            }

            $headerValue = $headers[$headerToCheck];

            if (strpos($headerValue, 't=') === 0) {
                $headerValue = substr($headerValue, 2);
            }

            $headerValueInSeconds = $this->convertAmbiguousTimestampToSeconds((float) $headerValue, $currentTimeInSeconds);

            if ($headerValueInSeconds === 0.0) {
                continue;
            }

            // Time tags should be in nanoseconds, so multiply seconds by 1e9 (1,000,000,000)
            $this->tag(
                Tag::TAG_QUEUE_TIME,
                ($this->timer->getStartAsMicrotime() - $headerValueInSeconds) * 1e9
            );
        }
    }

    public function stopIfRunning() : void
    {
        if ($this->timer->getStop() !== null) {
            return;
        }

        $this->stop();
    }

    public function stop(?float $overrideTimestamp = null, ?float $currentTime = null) : void
    {
        $this->timer->stop($overrideTimestamp);

        $this->tag(Tag::TAG_MEMORY_DELTA, MemoryUsage::record()->usedDifferenceInMegabytes($this->startMemory));
        $this->tag(Tag::TAG_REQUEST_PATH, $this->requestUriOverride ?? $this->determineRequestPathFromServerGlobal());

        $this->tagRequestIfRequestQueueTimeHeaderExists($currentTime ?? microtime(true));
    }

    /** @throws Exception */
    public function startSpan(string $operation, ?float $overrideTimestamp = null) : Span
    {
        $span = new Span($this->currentCommand, $operation, $this->id, $overrideTimestamp);

        $this->currentCommand->appendChild($span);

        $this->currentCommand = $span;

        return $span;
    }

    public function appendChild(Command $span) : void
    {
        $this->children[] = $span;
    }

    /**
     * Stop the currently "running" span.
     * You can still tag it if needed up until the request as a whole is finished.
     */
    public function stopSpan(?float $overrideTimestamp = null) : void
    {
        $command = $this->currentCommand;
        if (! $command instanceof Span) {
            $this->stop($overrideTimestamp);

            return;
        }

        $command->stop($overrideTimestamp);

        $this->currentCommand = $command->parent();
    }

    /**
     * Add a tag to the request as a whole
     *
     * @param mixed $value
     */
    public function tag(string $tagName, $value) : void
    {
        $this->appendChild(new TagRequest($tagName, $value, $this->id));
    }

    /**
     * turn this object into a list of commands to send to the CoreAgent
     *
     * @return array<string, array<string, array<int, array<string, (string|array|bool|null)>>>>
     */
    public function jsonSerialize() : array
    {
        $commands   = [];
        $commands[] = [
            'StartRequest' => [
                'request_id' => $this->id->toString(),
                'timestamp' => $this->timer->getStart(),
            ],
        ];

        foreach ($this->children as $child) {
            foreach ($child->jsonSerialize() as $value) {
                $commands[] = $value;
            }
        }

        $commands[] = [
            'FinishRequest' => [
                'request_id' => $this->id->toString(),
                'timestamp' => $this->timer->getStop(),
            ],
        ];

        return [
            'BatchCommand' => ['commands' => $commands],
        ];
    }

    public function collectedSpans() : int
    {
        return RecursivelyCountSpans::forCommands($this->children);
    }

    /**
     * You probably don't need this, it's used in testing.
     * Returns all events that have occurred in this Request.
     *
     * @internal
     * @deprecated
     *
     * @return Command[]|array<int, Command>
     *
     * @todo remove
     */
    public function getEvents() : array
    {
        return $this->children;
    }
}
