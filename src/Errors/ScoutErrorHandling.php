<?php

declare(strict_types=1);

namespace Scoutapm\Errors;

use ErrorException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Log\LoggerInterface;
use Scoutapm\Config;
use Scoutapm\Errors\ScoutClient\CompressPayload;
use Scoutapm\Errors\ScoutClient\ErrorReportingClient;
use Scoutapm\Errors\ScoutClient\HttpErrorReportingClient;
use Scoutapm\Events\Request\Request;
use Scoutapm\Helper\FindApplicationRoot;
use Scoutapm\Helper\LocateFileOrFolder;
use Throwable;

use function count;
use function error_get_last;
use function get_class;
use function in_array;
use function is_array;
use function register_shutdown_function;
use function set_exception_handler;
use function sprintf;

use const E_COMPILE_ERROR;
use const E_CORE_ERROR;
use const E_ERROR;
use const E_PARSE;
use const E_USER_ERROR;

final class ScoutErrorHandling implements ErrorHandling
{
    // @todo check these, they were copy/pasta from old client
    private const ERROR_TYPES_TO_CATCH = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    /** @var ErrorReportingClient */
    private $reportingClient;
    /** @var Config */
    private $config;
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

    public static function factory(Config $config, LoggerInterface $logger): self
    {
        return new self(
            new HttpErrorReportingClient(
                Psr18ClientDiscovery::find(),
                Psr17FactoryDiscovery::findRequestFactory(),
                Psr17FactoryDiscovery::findStreamFactory(),
                new CompressPayload(),
                $config,
                $logger,
                new FindApplicationRoot(new LocateFileOrFolder(), $config)
            ),
            $config,
            $logger
        );
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

    private function batchSendSize(): int
    {
        $batchSize = (int) $this->config->get(Config\ConfigKey::ERRORS_BATCH_SIZE);

        if ($batchSize <= 0) {
            return 1;
        }

        // @todo Should there be a max batch size, e.g. 20?
        return $batchSize;
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
        if (! $this->errorsEnabled()) {
            return;
        }

        $eventCount = count($this->errorEvents);
        foreach ($this->errorEvents as $errorEvent) {
            $this->reportingClient->sendErrorToScout($errorEvent);
        }

        $this->logger->debug(sprintf('Sent %d collected error event%s', $eventCount, $eventCount === 1 ? '' : 's'));
        $this->errorEvents = [];
    }

    public function handleException(Throwable $throwable): void
    {
        if ($this->errorsEnabled() && ! $this->isIgnoredException($throwable)) {
            $this->errorEvents[] = ErrorEvent::fromThrowable($this->request, $throwable);
            $this->sendCollectedErrors();
        }

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
