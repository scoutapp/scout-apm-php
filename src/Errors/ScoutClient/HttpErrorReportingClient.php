<?php

declare(strict_types=1);

namespace Scoutapm\Errors\ScoutClient;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;
use Scoutapm\Errors\ErrorEvent;
use Scoutapm\Helper\DetermineHostname\DetermineHostname;
use Scoutapm\Helper\FindApplicationRoot\FindApplicationRoot;
use Scoutapm\Helper\RootPackageGitSha\FindRootPackageGitSha;
use Scoutapm\Helper\Superglobals\Superglobals;

use function array_map;
use function count;
use function http_build_query;
use function json_encode;
use function rtrim;
use function sprintf;

final class HttpErrorReportingClient implements ErrorReportingClient
{
    private const SCOUT_REPORTING_PATH = '/apps/error.scout';

    private const SCOUT_ACCEPTED_STATUS_CODE = 202;
    private const SCOUT_TOO_MANY_REQUESTS    = 429;

    /** @var ClientInterface */
    private $client;
    /** @var RequestFactoryInterface */
    private $requestFactory;
    /** @var StreamFactoryInterface */
    private $streamFactory;
    /** @var CompressPayload */
    private $compressPayload;
    /** @var Config */
    private $config;
    /** @var LoggerInterface */
    private $logger;
    /** @var FindApplicationRoot */
    private $findApplicationRoot;
    /** @var string|null */
    private $memoizedErrorReportingServiceUrl;
    /** @var Superglobals */
    private $superglobals;
    /** @var DetermineHostname */
    private $determineHostname;
    /** @var FindRootPackageGitSha */
    private $findRootPackageGitSha;

    public function __construct(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        CompressPayload $compressPayload,
        Config $config,
        LoggerInterface $logger,
        FindApplicationRoot $findApplicationRoot,
        Superglobals $superglobals,
        DetermineHostname $determineHostname,
        FindRootPackageGitSha $findRootPackageGitSha
    ) {
        $this->client                = $client;
        $this->requestFactory        = $requestFactory;
        $this->streamFactory         = $streamFactory;
        $this->compressPayload       = $compressPayload;
        $this->config                = $config;
        $this->logger                = $logger;
        $this->findApplicationRoot   = $findApplicationRoot;
        $this->superglobals          = $superglobals;
        $this->determineHostname     = $determineHostname;
        $this->findRootPackageGitSha = $findRootPackageGitSha;
    }

    /** @inheritDoc */
    public function sendErrorToScout(array $errorEvents): void
    {
        try {
            $response = $this->client->sendRequest($this->psrRequestFromEvents($errorEvents));
        } catch (ClientExceptionInterface $clientException) {
            $this->logger->warning(
                sprintf('ErrorEvent could not be sent to Scout [ClientException]: %s', $clientException->getMessage()),
                ['exception' => $clientException]
            );

            return;
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode === self::SCOUT_TOO_MANY_REQUESTS) {
            $this->logger->notice(
                sprintf(
                    'Error send limit has been reached (HTTP %d returned from Error reporting service',
                    self::SCOUT_TOO_MANY_REQUESTS
                )
            );

            return;
        }

        if ($statusCode !== self::SCOUT_ACCEPTED_STATUS_CODE) {
            $this->logger->info(
                sprintf('ErrorEvent sending returned unexpected status code %d', $statusCode),
                [
                    'responseBody' => (string) $response->getBody(),
                ]
            );

            return;
        }

        $this->logger->debug(sprintf(
            'Sent %d error event%s to Scout Error Reporting',
            count($errorEvents),
            count($errorEvents) === 1 ? '' : 's'
        ));
    }

    private function errorReportingServiceUrl(): string
    {
        if ($this->memoizedErrorReportingServiceUrl === null) {
            $this->memoizedErrorReportingServiceUrl = rtrim((string) $this->config->get(ConfigKey::ERRORS_HOST), '/') . self::SCOUT_REPORTING_PATH;
        }

        return $this->memoizedErrorReportingServiceUrl;
    }

    /** @param non-empty-list<ErrorEvent> $errorEvent */
    private function psrRequestFromEvents(array $errorEvent): RequestInterface
    {
        return $this->requestFactory
            ->createRequest(
                'POST',
                $this->errorReportingServiceUrl() . '?' . http_build_query([
                    'key' => $this->config->get(ConfigKey::APPLICATION_KEY),
                    'name' => $this->config->get(ConfigKey::APPLICATION_NAME),
                ])
            )
            ->withHeader('Agent-Hostname', ($this->determineHostname)())
            ->withHeader('Content-Encoding', 'gzip') // Must be gzipped
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Error-Count', (string) count($errorEvent))
            ->withBody($this->streamFactory->createStream(
                ($this->compressPayload)(json_encode([
                    'notifier' => 'scout_apm_php',
                    'environment' => '',
                    'root' => ($this->findApplicationRoot)(),
                    'problems' => array_map(
                        function (ErrorEvent $errorEvent): array {
                            return $errorEvent->toJsonableArray(
                                $this->config,
                                $this->superglobals,
                                $this->determineHostname,
                                $this->findRootPackageGitSha
                            );
                        },
                        $errorEvent
                    ),
                ]))
            ));
    }
}
