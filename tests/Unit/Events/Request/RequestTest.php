<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events\Request;

use Exception;
use PHPUnit\Framework\TestCase;
use Scoutapm\Events\Request\Exception\SpanLimitReached;
use Scoutapm\Events\Request\Request;
use Scoutapm\Events\Request\RequestId;
use Scoutapm\Events\Span\Span;

use function array_key_exists;
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
    private const FIXED_POINT_UNIX_EPOCH_SECONDS = 1000000000.0;

    public function testExceptionThrownWhenSpanLimitReached(): void
    {
        $request = new Request();

        for ($i = 0; $i < 1500; $i++) {
            $request->startSpan(uniqid('test', true));
        }

        $this->expectException(SpanLimitReached::class);
        $this->expectExceptionMessage('the straw that broke the camel\'s back');
        $request->startSpan('the straw that broke the camel\'s back');
    }

    public function testCanBeStopped(): void
    {
        $request = new Request();

        self::assertNull(json_decode(json_encode($request), true)['BatchCommand']['commands'][1]['FinishRequest']['timestamp']);

        $request->stop();

        self::assertIsString(json_decode(json_encode($request), true)['BatchCommand']['commands'][3]['FinishRequest']['timestamp']);
    }

    public function testRequestIsStoppedIfRunning(): void
    {
        $request = new Request();

        self::assertNull(json_decode(json_encode($request), true)['BatchCommand']['commands'][1]['FinishRequest']['timestamp']);

        $request->stopIfRunning();

        self::assertIsString(json_decode(json_encode($request), true)['BatchCommand']['commands'][3]['FinishRequest']['timestamp']);
    }

    public function testRequestFinishTimestampIsNotChangedWhenStopIfRunningIsCalledOnAStoppedRequest(): void
    {
        $request = new Request();
        $request->stop(time() - 100.0);
        $originalStopTime = json_decode(json_encode($request), true)['BatchCommand']['commands'][3]['FinishRequest']['timestamp'];

        $request->stopIfRunning();

        self::assertSame($originalStopTime, json_decode(json_encode($request), true)['BatchCommand']['commands'][3]['FinishRequest']['timestamp']);
    }

    public function testMemoryUsageIsTaggedWhenRequestStopped(): void
    {
        $request = new Request();

        /** @noinspection PhpUnusedLocalVariableInspection */
        $block = str_repeat('a', 1000000);

        $request->stopIfRunning();

        $tagRequest = json_decode(json_encode($request), true)['BatchCommand']['commands'][1]['TagRequest'];

        self::assertSame('memory_delta', $tagRequest['tag']);
        self::assertGreaterThan(0, $tagRequest['value']);
    }

    public function testRequestUriFromServerGlobalIsTaggedWhenRequestStopped(): void
    {
        $_SERVER['REQUEST_URI'] = '/request-uri-from-server';

        $request = new Request();
        $request->stopIfRunning();

        $tagRequest = json_decode(json_encode($request), true)['BatchCommand']['commands'][2]['TagRequest'];

        self::assertSame('path', $tagRequest['tag']);
        self::assertSame('/request-uri-from-server', $tagRequest['value']);
    }

    public function testOrigPathInfoFromServerGlobalIsTaggedWhenRequestStopped(): void
    {
        $_SERVER['REQUEST_URI']    = null;
        $_SERVER['ORIG_PATH_INFO'] = '/orig-path-info-from-server';

        $request = new Request();
        $request->stopIfRunning();

        $tagRequest = json_decode(json_encode($request), true)['BatchCommand']['commands'][2]['TagRequest'];

        self::assertSame('path', $tagRequest['tag']);
        self::assertSame('/orig-path-info-from-server', $tagRequest['value']);
    }

    public function testRequestUriFromOverrideIsTaggedWhenRequestStopped(): void
    {
        $_SERVER['REQUEST_URI'] = '/request-uri-from-server';

        $request = new Request();
        $request->overrideRequestUri('/overridden-request-uri');
        $request->stopIfRunning();

        $tagRequest = json_decode(json_encode($request), true)['BatchCommand']['commands'][2]['TagRequest'];

        self::assertSame('path', $tagRequest['tag']);
        self::assertSame('/overridden-request-uri', $tagRequest['value']);
    }

    public function testJsonSerializes(): void
    {
        // Make a request with some interesting content.
        $request = new Request();
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
        $request = new Request();
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
        $_SERVER[$headerName] = $headerValue;

        // 0.005 = 5ms after epoch
        $request = new Request(self::FIXED_POINT_UNIX_EPOCH_SECONDS + 0.005);
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

        unset($_SERVER[$headerName]);
    }
}
