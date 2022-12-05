<?php

declare(strict_types=1);

namespace Scoutapm\Errors;

use ErrorException;
use Psr\Log\LoggerInterface;
use Scoutapm\Config;
use Scoutapm\Errors\ScoutClient\ErrorReportingClient;
use Scoutapm\Events\Request\Request;
use Throwable;

use function count;
use function error_get_last;
use function get_class;
use function in_array;
use function is_array;
use function register_shutdown_function;
use function set_exception_handler;
use function spl_object_hash;

use const E_COMPILE_ERROR;
use const E_CORE_ERROR;
use const E_ERROR;
use const E_PARSE;
use const E_USER_ERROR;

/** @internal This is not covered by BC promise */
final class ScoutErrorHandling implements ErrorHandling
{
    private const ERROR_TYPES_TO_CATCH = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    /** @var ErrorReportingClient */
    private $reportingClient;
    /** @var Config */
    private $config;
    /** @var list<string> */
    private $objectHashesOfHandledExceptions = [];
    /** @var list<ErrorEvent> */
    private $errorEvents = [];
    /** @var callable|null */
    private $oldExceptionHandler;
    /** @var ?Request */
    private $request;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(ErrorReportingClient $reportingClient, Config $config, LoggerInterface $logger)
    {
        $this->reportingClient = $reportingClient;
        $this->config          = $config;
        $this->logger          = $logger;
    }

    private function errorsEnabled(): bool
    {
        return (bool) $this->config->get(Config\ConfigKey::ERRORS_ENABLED);
    }

    private function isIgnoredException(Throwable $exception): bool
    {
        $ignoredExceptions = $this->config->get(Config\ConfigKey::ERRORS_IGNORED_EXCEPTIONS);

        if (! is_array($ignoredExceptions)) {
            return false;
        }

        return in_array(get_class($exception), $ignoredExceptions, true);
    }

    public function changeCurrentRequest(Request $request): void
    {
        $this->request = $request;
    }

    public function registerListeners(): void
    {
        if (! $this->errorsEnabled()) {
            $this->logger->debug('Error handling is not enabled, skipping registering listeners');

            return;
        }

        $this->oldExceptionHandler = set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function sendCollectedErrors(): void
    {
        if (! $this->errorsEnabled() || ! count($this->errorEvents)) {
            return;
        }

        $this->reportingClient->sendErrorToScout($this->errorEvents);

        $this->errorEvents = [];
    }

    public function recordThrowable(Throwable $throwable): void
    {
        if (! $this->errorsEnabled() || $this->isIgnoredException($throwable)) {
            return;
        }

        $thisThrowableObjectHash = spl_object_hash($throwable);

        // Storing the object hashes & checking means we don't send exactly the same exception twice in one request
        if (! in_array($thisThrowableObjectHash, $this->objectHashesOfHandledExceptions, true)) {
            $this->objectHashesOfHandledExceptions[] = $thisThrowableObjectHash;

            $this->errorEvents[] = ErrorEvent::fromThrowable($this->request, $throwable);
        }

        $this->sendCollectedErrors();
    }

    public function handleException(Throwable $throwable): void
    {
        $this->recordThrowable($throwable);

        if (! $this->oldExceptionHandler) {
            return;
        }

        ($this->oldExceptionHandler)($throwable);
    }

    public function handleShutdown(): void
    {
        if (! $this->errorsEnabled()) {
            return;
        }

        $error = error_get_last();
        if (is_array($error) && in_array($error['type'], self::ERROR_TYPES_TO_CATCH, true)) {
            $this->recordThrowable(new ErrorException(
                $error['message'],
                $error['type'],
                $error['type'],
                $error['file'],
                $error['line']
            ));
        }

        $this->sendCollectedErrors();
    }
}
