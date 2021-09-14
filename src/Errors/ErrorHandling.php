<?php

declare(strict_types=1);

namespace Scoutapm\Errors;

use ErrorException;
use Scoutapm\Agent;
use Scoutapm\Errors\ScoutClient\ErrorReportingClient;
use Throwable;

use function error_get_last;
use function in_array;
use function is_array;
use function register_shutdown_function;
use function set_exception_handler;

use const E_COMPILE_ERROR;
use const E_CORE_ERROR;
use const E_ERROR;
use const E_PARSE;
use const E_USER_ERROR;

class ErrorHandling
{
    // @todo check these, they were copy/pasta from old client
    private const ERROR_TYPES_TO_CATCH = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    /** @var Agent */
    private $agent;
    /** @var ErrorReportingClient */
    private $reportingClient;
    /** @var list<ErrorEvent> */
    private $errorEvents = [];
    /** @var callable|null */
    private $oldExceptionHandler;

    public function __construct(Agent $agent, ErrorReportingClient $reportingClient)
    {
        $this->agent           = $agent;
        $this->reportingClient = $reportingClient;
    }

    public function registerListeners(): void
    {
        $this->oldExceptionHandler = set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function sendCollectedErrors(): void
    {
        foreach ($this->errorEvents as $errorEvent) {
            $this->reportingClient->sendErrorToScout($errorEvent);
        }
    }

    public function handleException(Throwable $throwable): void
    {
        $requestId = $this->agent->requestId();

        if ($requestId !== null) { // @todo only if we shouldn't ignore it
            $this->errorEvents[] = ErrorEvent::fromThrowable($requestId, $throwable);
        }

        if (! $this->oldExceptionHandler) {
            return;
        }

        ($this->oldExceptionHandler)($throwable);
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();
        if (! is_array($error) || ! in_array($error['type'], self::ERROR_TYPES_TO_CATCH, true)) {
            return;
        }

        $this->handleException(new ErrorException(
            $error['message'],
            $error['type'],
            $error['type'],
            $error['file'],
            $error['line']
        ));
    }
}
