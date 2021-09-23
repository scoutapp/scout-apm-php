<?php

declare(strict_types=1);

namespace Scoutapm\Errors\ScoutClient;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;
use Scoutapm\Errors\ErrorEvent;
use Scoutapm\Helper\DetermineHostname;
use Scoutapm\Helper\FindApplicationRoot;
use Scoutapm\Helper\Superglobals;

use function array_map;
use function count;
use function http_build_query;
use function json_encode;
use function rtrim;
use function sprintf;

final class GuzzleErrorReportingClient implements ErrorReportingClient
{
    private const SCOUT_REPORTING_PATH = '/apps/error.scout';

    private const SCOUT_ACCEPTED_STATUS_CODE = 202;

    /** @var ClientInterface */
    private $client;
    /** @var CompressPayload */
    private $compressPayload;
    /** @var Config */
    private $config;
    /** @var LoggerInterface */
    private $logger;
    /** @var FindApplicationRoot */
    private $findApplicationRoot;
    /** @var string|null */
    private $memoizedErrorsUrl;

    public function __construct(
        ClientInterface $client,
        CompressPayload $compressPayload,
        Config $config,
        LoggerInterface $logger,
        FindApplicationRoot $findApplicationRoot
    ) {
        $this->client              = $client;
        $this->compressPayload     = $compressPayload;
        $this->config              = $config;
        $this->logger              = $logger;
        $this->findApplicationRoot = $findApplicationRoot;
    }

    public function sendErrorToScout(ErrorEvent $errorEvent): void // @todo check if we want to bulk send them - probably
    {
        // @todo we're not exposing any async functionality here, but for those with an event loop, that would be better
        $this->client
            ->sendAsync($this->psrRequestFromEvents([$errorEvent]))
            ->then(
                function (ResponseInterface $response): void {
                    $statusCode = $response->getStatusCode();
                    if ($statusCode !== self::SCOUT_ACCEPTED_STATUS_CODE) {
                        $this->logger->info(
                            sprintf('ErrorEvent sending returned unexpected status code %d', $statusCode),
                            [
                                'responseBody' => (string) $response->getBody(),
                            ]
                        );

                        return;
                    }

                    $this->logger->debug('Sent an error payload to Scout Error Reporting');
                },
                function (RequestException $requestException): void {
                    $this->logger->warning(
                        sprintf('ErrorEvent could not be sent to Scout: %s', $requestException->getMessage()),
                        ['exception' => $requestException]
                    );
                }
            )
            ->wait();
    }

    private function memoizedErrorsUrl(): string
    {
        if ($this->memoizedErrorsUrl === null) {
            $this->memoizedErrorsUrl = rtrim((string) $this->config->get(ConfigKey::ERRORS_HOST), '/') . self::SCOUT_REPORTING_PATH;
        }

        return $this->memoizedErrorsUrl;
    }

    /**
     * @param list<ErrorEvent> $errorEvent
     * // @todo Type check this to require at least one errorEvent
     */
    private function psrRequestFromEvents(array $errorEvent): RequestInterface
    {
        return new Request(
            'POST',
            $this->memoizedErrorsUrl() . '?' . http_build_query([
                'key' => $this->config->get(ConfigKey::APPLICATION_KEY),
                'name' => $this->config->get(ConfigKey::APPLICATION_NAME),
            ]),
            [
                'Agent-Hostname' => DetermineHostname::withConfig($this->config),
                'Content-Encoding' => 'gzip', // Must be gzipped
                'Content-Type' => 'application/json',
                'X-Error-Count' => (string) count($errorEvent),
            ],
            ($this->compressPayload)(json_encode([
                'notifier' => 'scout_apm_php',
                'environment' => '',
                'root' => ($this->findApplicationRoot)(),
                'problems' => array_map(
                    function (ErrorEvent $errorEvent): array {
                        return $errorEvent->toJsonableArray(
                            $this->config,
                            Superglobals::session(), // @todo probably inject session/env
                            Superglobals::env() // @todo probably inject session/env
                        );
                    },
                    $errorEvent
                ),
            ]))
        );
    }
}
