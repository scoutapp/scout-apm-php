<?php

declare(strict_types=1);

namespace Scoutapm\Errors;

use Scoutapm\Events\Request\Request;
use Throwable;

/** @internal This is not covered by BC promise */
interface ErrorHandling
{
    /**
     * Whenever the Agent's current request is changed, this should be called with the new request. The implementation
     * of this interface may use the "current request" information to supplement the error with metadata.
     *
     * If a request is not set at any point, ErrorHandling should not care, and should continue to operate. The
     * implementation should not rely on a request being always set.
     */
    public function changeCurrentRequest(Request $request): void;

    /**
     * The implementation should register itself as a PHP error handler, if desired.
     */
    public function registerListeners(): void;

    /**
     * If the implementation supports batching of collected errors, this method should send all collected errors
     * to the error reporting service.
     */
    public function sendCollectedErrors(): void;

    /**
     * This should be called to record information about an exception that was thrown. Example from a consumer
     * perspective might be:
     *
     * @example
     * try {
     *   somethingThatCausesAnException();
     * } catch(Throwable $t) {
     *   $errorHandling->recordThrowable($t);
     *   throw $t; // or otherwise handle/log/etc. depending on requirements
     * }
     */
    public function recordThrowable(Throwable $throwable): void;
}
