<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events\Request;

use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Scoutapm\Config;
use Scoutapm\Events\Request\Exception\SpanLimitReached;
use Scoutapm\Events\Request\Request;
use Scoutapm\Events\Request\RequestId;
use Scoutapm\Events\Span\Span;
use Scoutapm\Helper\FindRequestHeaders\FindRequestHeadersUsingServerGlobal;
use Scoutapm\Helper\Superglobals\Superglobals;
use Scoutapm\UnitTests\TestHelper;

use function array_key_exists;
use function array_map;
use function assert;
use function json_decode;
use function json_encode;
use function next;
use function reset;
use function sprintf;
use function str_repeat;
use function time;
use function uniqid;

/** @covers \Scoutapm\Events\Request\Request */
final class RequestTest extends TestCase
{
    private const EXPECTED_SPAN_LIMIT = 3000;

    private const FIXED_POINT_UNIX_EPOCH_SECONDS = 1000000000.0;

    /** @var Superglobals&MockObject */
    private $superglobals;

    public function setUp(): void
    {
        parent::setUp();

        $this->superglobals = $this->createMock(Superglobals::class);
    }

    /** @psalm-param array<string, mixed> $configOverrides */
    private function requestFromConfiguration(array $configOverrides = [], ?float $overrideTime = null): Request
    {
        return Request::fromConfigAndOverrideTime(
            Config::fromArray($configOverrides),
            new FindRequestHeadersUsingServerGlobal($this->superglobals),
            $overrideTime
        );
    }

    public function testRequestHasDifferentId(): void
    {
        self::assertNotEquals(
            $this->requestFromConfiguration()->id(),
            $this->requestFromConfiguration()->id()
        );
    }

    public function testExceptionThrownWhenSpanLimitReached(): void
    {
        $request = $this->requestFromConfiguration();

        for ($i = 0; $i < self::EXPECTED_SPAN_LIMIT; $i++) {
            $request->startSpan(uniqid('test', true));
        }

        $this->expectException(SpanLimitReached::class);
        $this->expectExceptionMessage('the straw that broke the camel\'s back');
        $request->startSpan('the straw that broke the camel\'s back');
    }

    public function testCanBeStopped(): void
    {
        $request = $this->requestFromConfiguration();

        self::assertNull(json_decode(json_encode($request), true)['BatchCommand']['commands'][1]['FinishRequest']['timestamp']);

        $request->stop();

        self::assertIsString(json_decode(json_encode($request), true)['BatchCommand']['commands'][3]['FinishRequest']['timestamp']);
    }

    public function testRequestIsStoppedIfRunning(): void
    {
        $request = $this->requestFromConfiguration();

        self::assertNull(json_decode(json_encode($request), true)['BatchCommand']['commands'][1]['FinishRequest']['timestamp']);

        $request->stopIfRunning();

        self::assertIsString(json_decode(json_encode($request), true)['BatchCommand']['commands'][3]['FinishRequest']['timestamp']);
    }

    public function testRequestFinishTimestampIsNotChangedWhenStopIfRunningIsCalledOnAStoppedRequest(): void
    {
        $request = $this->requestFromConfiguration();
        $request->stop(time() - 100.0);
        $originalStopTime = json_decode(json_encode($request), true)['BatchCommand']['commands'][3]['FinishRequest']['timestamp'];

        $request->stopIfRunning();

        self::assertSame($originalStopTime, json_decode(json_encode($request), true)['BatchCommand']['commands'][3]['FinishRequest']['timestamp']);
    }

    public function testMemoryUsageIsTaggedWhenRequestStopped(): void
    {
        $request = $this->requestFromConfiguration();

        /** @noinspection PhpUnusedLocalVariableInspection */
        $block = str_repeat('a', 1000000);

        $request->stopIfRunning();

        $tagRequest = json_decode(json_encode($request), true)['BatchCommand']['commands'][1]['TagRequest'];

        self::assertSame('memory_delta', $tagRequest['tag']);
        self::assertGreaterThan(0, $tagRequest['value']);
    }

    public function testRequestUriFromServerGlobalIsTaggedWhenRequestStoppedWithParametersRemovedByDefault(): void
    {
        $_SERVER['REQUEST_URI'] = '/request-uri-from-server?a=1&b=2';

        $request = $this->requestFromConfiguration();
        $request->stopIfRunning();

        $tagRequest = json_decode(json_encode($request), true)['BatchCommand']['commands'][2]['TagRequest'];

        self::assertSame('path', $tagRequest['tag']);
        self::assertSame('/request-uri-from-server', $tagRequest['value']);
    }

    public function testOrigPathInfoFromServerGlobalIsTaggedWhenRequestStopped(): void
    {
        $_SERVER['REQUEST_URI']    = null;
        $_SERVER['ORIG_PATH_INFO'] = '/orig-path-info-from-server';

        $request = $this->requestFromConfiguration();
        $request->stopIfRunning();

        $tagRequest = json_decode(json_encode($request), true)['BatchCommand']['commands'][2]['TagRequest'];

        self::assertSame('path', $tagRequest['tag']);
        self::assertSame('/orig-path-info-from-server', $tagRequest['value']);
    }

    public function testRequestUriFromOverrideIsTaggedWhenRequestStopped(): void
    {
        $_SERVER['REQUEST_URI'] = '/request-uri-from-server?a=1&b=2';

        $request = $this->requestFromConfiguration();
        $request->overrideRequestUri('/overridden-request-uri');
        $request->stopIfRunning();

        $tagRequest = json_decode(json_encode($request), true)['BatchCommand']['commands'][2]['TagRequest'];

        self::assertSame('path', $tagRequest['tag']);
        self::assertSame('/overridden-request-uri', $tagRequest['value']);
    }

    public function testRequestUriQueryParametersAreNotRemovedWhenFullPathUriReportingIsUsed(): void
    {
        $_SERVER['REQUEST_URI'] = '/request-uri-from-server?a=1&b=2';

        $request = $this->requestFromConfiguration([
            Config\ConfigKey::URI_REPORTING => Config\ConfigKey::URI_REPORTING_FULL_PATH,
        ]);
        $request->stopIfRunning();

        $tagRequest = json_decode(json_encode($request), true)['BatchCommand']['commands'][2]['TagRequest'];

        self::assertSame('path', $tagRequest['tag']);
        self::assertSame('/request-uri-from-server?a=1&b=2', $tagRequest['value']);
    }

    /**
     * @return string[][]
     *
     * @psalm-return list<array{0: string}>
     */
    public function defaultFilteredParameterNamesProvider(): array
    {
        return array_map(
            static function (string $item): array {
                return [$item];
            },
            [
                'access',
                'access_token',
                'api_key',
                'apikey',
                'auth',
                'auth_token',
                'card[number]',
                'certificate',
                'credentials',
                'crypt',
                'key',
                'mysql_pwd',
                'otp',
                'passwd',
                'password',
                'private',
                'protected',
                'salt',
                'secret',
                'ssn',
                'stripetoken',
                'token',
            ]
        );
    }

    /**
     * @dataProvider defaultFilteredParameterNamesProvider
     */
    public function testRequestUriDefaultQueryParametersAreFilteredWhenFilteringEnabled(string $parameterName): void
    {
        $_SERVER['REQUEST_URI'] = '/request-uri-from-server?' . $parameterName . '=someValue';

        $request = $this->requestFromConfiguration([
            Config\ConfigKey::URI_REPORTING => Config\ConfigKey::URI_REPORTING_FILTERED,
        ]);
        $request->stopIfRunning();

        $tagRequest = json_decode(json_encode($request), true)['BatchCommand']['commands'][2]['TagRequest'];

        self::assertSame('path', $tagRequest['tag']);
        self::assertSame('/request-uri-from-server', $tagRequest['value']);
    }

    public function testRequestUriCustomQueryParametersAreFilteredWhenFilteringEnabled(): void
    {
        $_SERVER['REQUEST_URI'] = '/request-uri-from-server?aFilteredParam=someValue&notFiltered=anotherValue';

        $request = $this->requestFromConfiguration([
            Config\ConfigKey::URI_REPORTING => Config\ConfigKey::URI_REPORTING_FILTERED,
            Config\ConfigKey::URI_FILTERED_PARAMETERS => ['aFilteredParam'],
        ]);
        $request->stopIfRunning();

        $tagRequest = json_decode(json_encode($request), true)['BatchCommand']['commands'][2]['TagRequest'];

        self::assertSame('path', $tagRequest['tag']);
        self::assertSame('/request-uri-from-server?notFiltered=anotherValue', $tagRequest['value']);
    }

    public function testJsonSerializes(): void
    {
        // Make a request with some interesting content.
        $request = $this->requestFromConfiguration();
        $request->tag('t', 'v');
        $span = $request->startSpan('foo');
        $span->tag('spantag', 'spanvalue');
        $request->stopSpan();
        $request->stop();

        $serialized = $request->jsonSerialize();

        self::assertArrayHasKey('BatchCommand', $serialized);
        self::assertArrayHasKey('commands', $serialized['BatchCommand']);
        $commands = $serialized['BatchCommand']['commands'];

        self::assertArrayHasKey('StartRequest', reset($commands));
        self::assertArrayHasKey('TagRequest', next($commands));

        self::assertArrayHasKey('StartSpan', next($commands));
        self::assertArrayHasKey('TagSpan', next($commands));
        self::assertArrayHasKey('StopSpan', next($commands));

        self::assertArrayHasKey('TagRequest', next($commands));
        self::assertArrayHasKey('TagRequest', next($commands));

        self::assertArrayHasKey('FinishRequest', next($commands));
    }

    /** @throws Exception */
    public function testSpansCanBeCounted(): void
    {
        $request = $this->requestFromConfiguration();
        $request->tag('t', 'v');
        $span = $request->startSpan('foo');
        $span->tag('spantag', 'spanvalue');
        $span->appendChild(new Span($span, 'sub', RequestId::new()));
        $request->stopSpan();
        $request->stop();

        self::assertSame(2, $request->collectedSpans());
    }

    /** @return string[][] */
    public function queueTimeRequestHeadersProvider(): array
    {
        return [
            'requestStartMilliseconds' => [
                'headerName' => 'HTTP_X_REQUEST_START',
                'headerValue' => sprintf('%d', (self::FIXED_POINT_UNIX_EPOCH_SECONDS * 1000) + 2),
            ],
            'requestStartTEqualsMilliseconds' => [
                'headerName' => 'HTTP_X_REQUEST_START',
                'headerValue' => sprintf('t=%d', (self::FIXED_POINT_UNIX_EPOCH_SECONDS * 1000) + 2),
            ],
            'queueStartMilliseconds' => [
                'headerName' => 'HTTP_X_QUEUE_START',
                'headerValue' => sprintf('%d', (self::FIXED_POINT_UNIX_EPOCH_SECONDS * 1000) + 2),
            ],
            'queueStartTEqualsMilliseconds' => [
                'headerName' => 'HTTP_X_QUEUE_START',
                'headerValue' => sprintf('t=%d', (self::FIXED_POINT_UNIX_EPOCH_SECONDS * 1000) + 2),
            ],
            'requestStartSeconds' => [
                'headerName' => 'HTTP_X_REQUEST_START',
                'headerValue' => sprintf('%.3f', self::FIXED_POINT_UNIX_EPOCH_SECONDS + 0.002),
            ],
            'requestStartMicroseconds' => [
                'headerName' => 'HTTP_X_REQUEST_START',
                'headerValue' => sprintf('%d', (self::FIXED_POINT_UNIX_EPOCH_SECONDS * 1000000) + 2000),
            ],
            'requestStartNanoseconds' => [
                'headerName' => 'HTTP_X_REQUEST_START',
                'headerValue' => sprintf('%d', (self::FIXED_POINT_UNIX_EPOCH_SECONDS * 1000000000) + 2000000),
            ],
            'requestStartWhenNotPrefixedWithHttp' => [
                'headerName' => 'X-Request-Start',
                'headerValue' => sprintf('%d', (self::FIXED_POINT_UNIX_EPOCH_SECONDS * 1000) + 2),
            ],
            'requestStartWhenNotPrefixedWithHttpLowercase' => [
                'headerName' => 'x-request-start',
                'headerValue' => sprintf('%d', (self::FIXED_POINT_UNIX_EPOCH_SECONDS * 1000) + 2),
            ],
        ];
    }

    /**
     * @throws Exception
     *
     * @dataProvider queueTimeRequestHeadersProvider
     */
    public function testRequestIsTaggedWithQueueTime(string $headerName, string $headerValue): void
    {
        // 2 = 2ms after epoch
        $this->superglobals->method('server')->willReturn([$headerName => $headerValue]);

        // 0.005 = 5ms after epoch
        $request = $this->requestFromConfiguration([], self::FIXED_POINT_UNIX_EPOCH_SECONDS + 0.005);
        $request->stop(null, self::FIXED_POINT_UNIX_EPOCH_SECONDS);

        $f = $request->jsonSerialize();

        $foundTag = false;
        foreach ($f['BatchCommand']['commands'] as $command) {
            if (! array_key_exists('TagRequest', $command) || $command['TagRequest']['tag'] !== 'scout.queue_time_ns') {
                continue;
            }

            // float rounding errors, yay!
            self::assertSame(3000020, (int) $command['TagRequest']['value']);
            $foundTag = true;
        }

        self::assertTrue($foundTag, 'Could not find queue time tag');
    }

    public function testSpansAreNotRecordedBelowLeafSpans(): void
    {
        $request = $this->requestFromConfiguration();

        $request->startSpan('Foo', null, true);
        $request->startSpan('ShouldNotBeRecorded1');
        $request->startSpan('ShouldNotBeRecorded2');
        $request->startSpan('ShouldNotBeRecorded3');
        $request->stopSpan();
        $request->stopSpan();
        $request->startSpan('ShouldNotBeRecorded4');
        $request->stopSpan();
        $request->stopSpan();
        $request->startSpan('ShouldNotBeRecorded5');
        $request->stopSpan();
        $request->stopSpan();

        self::assertSame(1, $request->collectedSpans());

        $firstSpan = TestHelper::firstChildForCommand($request);
        assert($firstSpan instanceof Span);
        self::assertSame('Foo', $firstSpan->getName());

        $children = TestHelper::childrenForCommand($firstSpan);
        self::assertCount(0, $children);
    }

    public function testTagsCanBeRetrieved(): void
    {
        $request = $this->requestFromConfiguration();

        $request->startSpan('Foo');
        $request->tag('a', 'a');
        $request->tag('b', 'b');
        $request->stopSpan();

        self::assertEquals(
            [
                'a' => 'a',
                'b' => 'b',
            ],
            $request->tags()
        );
    }

    /**
     * @psalm-return list<array{0: list<string>, 1: ?string}>
     */
    public function controllerNameProvider(): array
    {
        return [
            [['Controller/Foo'], 'Controller/Foo'],
            [['Interesting/Span', 'Controller/Foo'], 'Controller/Foo'],
            [['Job/Bar'], 'Job/Bar'],
            [['Interesting/Span', 'Job/Bar'], 'Job/Bar'],
            // Whichever Controller/Job comes first is the one that is picked
            [['Controller/Foo', 'Job/Bar'], 'Controller/Foo'],
            [['Interesting/Span', 'Controller/Foo', 'Job/Bar'], 'Controller/Foo'],
            [['Job/Bar', 'Controller/Foo'], 'Job/Bar'],
            [['Interesting/Span', 'Job/Bar', 'Controller/Foo'], 'Job/Bar'],
            [['Middleware/Baz'], null],
            [['Interesting/Span', 'Middleware/Baz'], null],
            [[], null],
        ];
    }

    /**
     * @param list<string> $spans
     *
     * @dataProvider controllerNameProvider
     */
    public function testControllerNameIsDetermined(array $spans, ?string $expectedName): void
    {
        $request = $this->requestFromConfiguration();

        foreach ($spans as $span) {
            $request->startSpan($span);
            $request->stopSpan();
        }

        self::assertSame($expectedName, $request->controllerOrJobName());
    }

    public function testRequestPathCanBeDetermined(): void
    {
        self::markTestIncomplete(__METHOD__); // @todo needs tests
    }
}
