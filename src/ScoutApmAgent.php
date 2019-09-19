<?php

declare(strict_types=1);

namespace Scoutapm;

use Closure;
use Exception;
use Scoutapm\Events\Request\Request;
use Scoutapm\Events\Span\Span;

interface ScoutApmAgent
{
    public function connect() : void;

    /**
     * Returns true/false on if the agent should attempt to start and collect data.
     */
    public function enabled() : bool;

    /**
     * Starts a fromRequest span on the current request.
     *
     * NOTE: Do not call stop on the span itself, use the stopSpan() function
     * here to ensure the whole system knows its stopped
     *
     * @param string $operation         The "name" of the span, something like "Controller/User" or "SQL/Query"
     * @param ?float $overrideTimestamp If you need to set the start time to something specific
     *
     * @throws Exception
     */
    public function startSpan(string $operation, ?float $overrideTimestamp = null) : Span;

    public function stopSpan() : void;

    /** @return mixed */
    public function instrument(string $type, string $name, Closure $block);

    /** @return mixed */
    public function webTransaction(string $name, Closure $block);

    /** @return mixed */
    public function backgroundTransaction(string $name, Closure $block);

    public function addContext(string $tag, string $value) : void;

    public function tagRequest(string $tag, string $value) : void;

    /**
     * Check if a given URL was configured as ignored.
     * Does not alter the running request. If you wish to abort tracing of this
     * request, use ignore()
     */
    public function ignored(string $path) : bool;

    /**
     * Mark the running request as ignored. Triggers optimizations in various
     * tracing and tagging methods to turn them into NOOPs
     */
    public function ignore() : void;

    /**
     * Returns true only if the request was sent onward to the core agent. False otherwise.
     *
     * @throws Exception
     */
    public function send() : bool;

    /**
     * You probably don't need this, it's useful in testing
     *
     * @internal
     * @deprecated
     */
    public function getRequest() : ?Request;
}
