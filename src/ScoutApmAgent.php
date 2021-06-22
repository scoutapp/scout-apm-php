<?php

declare(strict_types=1);

namespace Scoutapm;

use Exception;
use Scoutapm\Events\Request\Request;
use Scoutapm\Events\Span\SpanReference;

interface ScoutApmAgent
{
    public function connect(): void;

    /**
     * Returns true/false on if the agent should attempt to start and collect data.
     */
    public function enabled(): bool;

    /**
     * Starts a fromRequest span on the current request.
     *
     * NOTE: Do not call stop on the span itself, use the stopSpan() function
     * here to ensure the whole system knows its stopped
     *
     * If the span limit has been reached, or is there no active request, this will return `null`. Consumers *MUST*
     * check for `null` if using the Span returned.
     *
     * @param string $operation         The "name" of the span, something like "Controller/User" or "SQL/Query"
     * @param ?float $overrideTimestamp If you need to set the start time to something specific
     * @param bool   $leafSpan          A leaf span will not have any child spans included on serialization (except tags)
     *
     * @throws Exception
     */
    public function startSpan(string $operation, ?float $overrideTimestamp = null, bool $leafSpan = false): ?SpanReference;

    public function stopSpan(): void;

    /**
     * @return mixed
     *
     * @psalm-template T
     * @psalm-param callable(?SpanReference): T $block
     * @psalm-return T
     */
    public function instrument(string $type, string $name, callable $block);

    /**
     * @return mixed
     *
     * @psalm-template T
     * @psalm-param callable(?SpanReference): T $block
     * @psalm-return T
     */
    public function webTransaction(string $name, callable $block);

    /**
     * @return mixed
     *
     * @psalm-template T
     * @psalm-param callable(?SpanReference): T $block
     * @psalm-return T
     */
    public function backgroundTransaction(string $name, callable $block);

    public function addContext(string $tag, string $value): void;

    public function tagRequest(string $tag, string $value): void;

    /**
     * Check if a given URL was configured as ignored.
     * Does not alter the running request. If you wish to abort tracing of this
     * request, use ignore()
     */
    public function ignored(string $path): bool;

    /**
     * Mark the running request as ignored. Triggers optimizations in various
     * tracing and tagging methods to turn them into NOOPs
     */
    public function ignore(): void;

    /**
     * Should the instrumentation be enabled for a particular functionality. This checks the `disabled_instruments`
     * configuration - if an instrumentation is not explicitly disabled, this will return true.
     *
     * The list of functionality that can be disabled depends on the library binding being used.
     */
    public function shouldInstrument(string $functionality): bool;

    /**
     * If the automatically determined request URI is incorrect, please report an issue so we can investigate. You may
     * override the automatic determination of the request URI by calling this method.
     *
     * @link https://github.com/scoutapp/scout-apm-php
     */
    public function changeRequestUri(string $newRequestUri): void;

    /**
     * Returns true only if the request was sent onward to the core agent. False otherwise.
     *
     * @throws Exception
     */
    public function send(): bool;

    /**
     * Clears any currently recorded request data/spans, and start a new request.
     */
    public function startNewRequest(): void;

    /**
     * You probably don't need this, it's useful in testing
     *
     * @internal
     * @deprecated
     */
    public function getRequest(): ?Request;
}
