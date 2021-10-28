<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Errors\ScoutClient;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Scoutapm\Config;
use Scoutapm\Errors\ErrorEvent;
use Scoutapm\Errors\ScoutClient\CompressPayload;
use Scoutapm\Errors\ScoutClient\HttpErrorReportingClient;
use Scoutapm\Events\Request\Request;
use Scoutapm\Helper\DetermineHostname\DetermineHostname;
use Scoutapm\Helper\FindApplicationRoot\FindApplicationRoot;
use Scoutapm\Helper\FindRequestHeaders\FindRequestHeaders;
use Scoutapm\Helper\RootPackageGitSha\FindRootPackageGitSha;
use Scoutapm\Helper\Superglobals\Superglobals;
use Scoutapm\Helper\Superglobals\SuperglobalsArrays;
use Scoutapm\UnitTests\TestLogger;

use function json_decode;

/** @covers \Scoutapm\Errors\ScoutClient\HttpErrorReportingClient */
final class HttpErrorReportingClientTest extends TestCase
{
    /** @var ClientInterface&MockObject */
    private $client;
    /** @var CompressPayload&MockObject */
    private $compressPayload;
    /** @var Config */
    private $config;
    /** @var TestLogger */
    private $logger;
    /** @var FindApplicationRoot&MockObject */
    private $findApplicationRoot;
    /** @var Superglobals */
    private $superglobals;
    /** @var DetermineHostname&MockObject */
    private $determineHostname;
    /** @var FindRootPackageGitSha&MockObject */
    private $findRootPackageGitSha;
    /** @var HttpErrorReportingClient */
    private $errorReportingClient;

    public function setUp(): void
    {
        parent::setUp();

        $psr17Factory = new Psr17Factory();

        $this->client                = $this->createMock(ClientInterface::class);
        $this->compressPayload       = $this->createMock(CompressPayload::class);
        $this->config                = Config::fromArray([]);
        $this->logger                = new TestLogger();
        $this->findApplicationRoot   = $this->createMock(FindApplicationRoot::class);
        $this->superglobals          = new SuperglobalsArrays([], [], [], []);
        $this->determineHostname     = $this->createMock(DetermineHostname::class);
        $this->findRootPackageGitSha = $this->createMock(FindRootPackageGitSha::class);

        $this->errorReportingClient = new HttpErrorReportingClient(
            $this->client,
            $psr17Factory,
            $psr17Factory,
            $this->compressPayload,
            $this->config,
            $this->logger,
            $this->findApplicationRoot,
            $this->superglobals,
            $this->determineHostname,
            $this->findRootPackageGitSha
        );
    }

    public function testSendingErrorToScoutHappyPath(): void
    {
        $errorEvent = ErrorEvent::fromThrowable(
            Request::fromConfigAndOverrideTime($this->config, $this->createMock(FindRequestHeaders::class)),
            new RuntimeException('things')
        );

        $this->findApplicationRoot->method('__invoke')->willReturn('/path/to/app');
        $this->compressPayload->method('__invoke')->willReturnArgument(0);
        $this->determineHostname->method('__invoke')->willReturn('www.friendface.com');

        /** @noinspection PhpParamsInspection */
        $this->client
            ->expects(self::once())
            ->method('sendRequest')
            ->with(self::callback(static function (RequestInterface $request): bool {
                self::assertSame('https://errors.scoutapm.com/apps/error.scout', (string) $request->getUri());
                self::assertSame('www.friendface.com', $request->getHeaderLine('Agent-Hostname'));
                self::assertSame('gzip', $request->getHeaderLine('Content-Encoding'));
                self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
                self::assertSame('1', $request->getHeaderLine('X-Error-Count'));

                /**
                 * Only check the "outer" structure here, the `problems` format is checked by {@see ErrorEventTest}
                 */
                $decodedJsonBody = json_decode((string) $request->getBody(), true);

                self::assertIsArray($decodedJsonBody);

                self::assertArrayHasKey('notifier', $decodedJsonBody);
                self::assertSame('scout_apm_php', $decodedJsonBody['notifier']);

                self::assertArrayHasKey('environment', $decodedJsonBody);
                self::assertSame('', $decodedJsonBody['environment']); // Intentionally not set for now

                self::assertArrayHasKey('root', $decodedJsonBody);
                self::assertSame('/path/to/app', $decodedJsonBody['root']);

                self::assertArrayHasKey('problems', $decodedJsonBody);
                self::assertIsArray($decodedJsonBody['problems']);
                self::assertCount(1, $decodedJsonBody['problems']);

                return true;
            }))
            ->willReturn(
                (new Psr17Factory())->createResponse(202)
            );

        $this->errorReportingClient->sendErrorToScout($errorEvent);

        self::assertTrue($this->logger->hasDebugThatContains('Sent an error payload to Scout Error Reporting'));
    }

    public function testSendingErrorToScoutLogsFailureToInfo(): void
    {
        $errorEvent = ErrorEvent::fromThrowable(
            Request::fromConfigAndOverrideTime($this->config, $this->createMock(FindRequestHeaders::class)),
            new RuntimeException('things')
        );

        /** @noinspection PhpParamsInspection */
        $this->client
            ->expects(self::once())
            ->method('sendRequest')
            ->with(self::isInstanceOf(RequestInterface::class))
            ->willReturn(
                (new Psr17Factory())->createResponse(500)
            );

        $this->errorReportingClient->sendErrorToScout($errorEvent);

        self::assertTrue($this->logger->hasInfoThatContains('ErrorEvent sending returned unexpected status code 500'));
    }

    public function testSendingErrorToScoutLogsClientExceptionInfo(): void
    {
        $errorEvent = ErrorEvent::fromThrowable(
            Request::fromConfigAndOverrideTime($this->config, $this->createMock(FindRequestHeaders::class)),
            new RuntimeException('things')
        );

        /** @noinspection PhpParamsInspection */
        $this->client
            ->expects(self::once())
            ->method('sendRequest')
            ->with(self::isInstanceOf(RequestInterface::class))
            ->willThrowException(new class ('oh no') extends RuntimeException implements ClientExceptionInterface {
            });

        $this->errorReportingClient->sendErrorToScout($errorEvent);

        self::assertTrue($this->logger->hasWarningThatContains('ErrorEvent could not be sent to Scout [ClientException]: oh no'));
    }
}
