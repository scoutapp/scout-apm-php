<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events\Request;

use Exception;
use PHPUnit\Framework\TestCase;
use Scoutapm\Events\Request\Request;
use Scoutapm\Events\Request\RequestId;
use Scoutapm\Events\Span\Span;
use function array_key_exists;
use function json_decode;
use function json_encode;
use function next;
use function reset;
use function str_repeat;
use function time;

/** @covers \Scoutapm\Events\Request\Request */
final class RequestTest extends TestCase
{
    public function testCanBeInitialized() : void
    {
        $request = new Request();
        self::assertNotNull($request);
    }

    public function testCanBeStopped() : void
    {
        $request = new Request();

        self::assertNull(json_decode(json_encode($request), true)['BatchCommand']['commands'][1]['FinishRequest']['timestamp']);

        $request->stop();

        self::assertIsString(json_decode(json_encode($request), true)['BatchCommand']['commands'][3]['FinishRequest']['timestamp']);
    }

    public function testRequestIsStoppedIfRunning() : void
    {
        $request = new Request();

        self::assertNull(json_decode(json_encode($request), true)['BatchCommand']['commands'][1]['FinishRequest']['timestamp']);

        $request->stopIfRunning();

        self::assertIsString(json_decode(json_encode($request), true)['BatchCommand']['commands'][3]['FinishRequest']['timestamp']);
    }

    public function testRequestFinishTimestampIsNotChangedWhenStopIfRunningIsCalledOnAStoppedRequest() : void
    {
        $request = new Request();
        $request->stop(time() - 100.0);
        $originalStopTime = json_decode(json_encode($request), true)['BatchCommand']['commands'][3]['FinishRequest']['timestamp'];

        $request->stopIfRunning();

        self::assertSame($originalStopTime, json_decode(json_encode($request), true)['BatchCommand']['commands'][3]['FinishRequest']['timestamp']);
    }

    public function testMemoryUsageIsTaggedWhenRequestStopped() : void
    {
        $request = new Request();

        /** @noinspection PhpUnusedLocalVariableInspection */
        $block = str_repeat('a', 1000000);

        $request->stopIfRunning();

        $tagRequest = json_decode(json_encode($request), true)['BatchCommand']['commands'][1]['TagRequest'];

        self::assertSame('memory_delta', $tagRequest['tag']);
        self::assertGreaterThan(0, $tagRequest['value']);
    }

    public function testRequestUriFromServerGlobalIsTaggedWhenRequestStopped() : void
    {
        $_SERVER['REQUEST_URI'] = '/request-uri-from-server';

        $request = new Request();
        $request->stopIfRunning();

        $tagRequest = json_decode(json_encode($request), true)['BatchCommand']['commands'][2]['TagRequest'];

        self::assertSame('path', $tagRequest['tag']);
        self::assertSame('/request-uri-from-server', $tagRequest['value']);
    }

    public function testOrigPathInfoFromServerGlobalIsTaggedWhenRequestStopped() : void
    {
        $_SERVER['REQUEST_URI']    = null;
        $_SERVER['ORIG_PATH_INFO'] = '/orig-path-info-from-server';

        $request = new Request();
        $request->stopIfRunning();

        $tagRequest = json_decode(json_encode($request), true)['BatchCommand']['commands'][2]['TagRequest'];

        self::assertSame('path', $tagRequest['tag']);
        self::assertSame('/orig-path-info-from-server', $tagRequest['value']);
    }

    public function testRequestUriFromOverrideIsTaggedWhenRequestStopped() : void
    {
        $_SERVER['REQUEST_URI'] = '/request-uri-from-server';

        $request = new Request();
        $request->overrideRequestUri('/overridden-request-uri');
        $request->stopIfRunning();

        $tagRequest = json_decode(json_encode($request), true)['BatchCommand']['commands'][2]['TagRequest'];

        self::assertSame('path', $tagRequest['tag']);
        self::assertSame('/overridden-request-uri', $tagRequest['value']);
    }

    public function testJsonSerializes() : void
    {
        // Make a request with some interesting content.
        $request = new Request();
        $request->tag('t', 'v');
        $span = $request->startSpan('foo');
        $span->tag('spantag', 'spanvalue');
        $request->stopSpan();
        $request->stop();

        $serialized = $request->jsonSerialize();
        self::assertIsArray($serialized);

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
    public function testSpansCanBeCounted() : void
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
    public function queueTimeRequestHeadersProvider() : array
    {
        return [
            'requestStartMilliseconds' => [
                'headerName' => 'HTTP_X_REQUEST_START',
                'headerValue' => '2',
                'currentTimeSeconds' => 0.0,
                'requestStartTimeSeconds' => 0.005,
                'expectedQueueTimeNanoseconds' => 3000000.0,
            ],
            'requestStartTEqualsMilliseconds' => [
                'headerName' => 'HTTP_X_REQUEST_START',
                'headerValue' => 't=2',
                'currentTimeSeconds' => 0.0,
                'requestStartTimeSeconds' => 0.005,
                'expectedQueueTimeNanoseconds' => 3000000.0,
            ],
            'queueStartMilliseconds' => [
                'headerName' => 'HTTP_X_QUEUE_START',
                'headerValue' => '2',
                'currentTimeSeconds' => 0.0,
                'requestStartTimeSeconds' => 0.005,
                'expectedQueueTimeNanoseconds' => 3000000.0,
            ],
            'queueStartTEqualsMilliseconds' => [
                'headerName' => 'HTTP_X_QUEUE_START',
                'headerValue' => 't=2',
                'currentTimeSeconds' => 0.0,
                'requestStartTimeSeconds' => 0.005,
                'expectedQueueTimeNanoseconds' => 3000000.0,
            ],
        ];
    }

    /**
     * @throws Exception
     *
     * @dataProvider queueTimeRequestHeadersProvider
     */
    public function testRequestIsTaggedWithQueueTime(
        string $headerName,
        string $headerValue,
        float $currentTimeSeconds,
        float $requestStartTimeSeconds,
        float $expectedQueueTimeNanoseconds
    ) : void {
        // 2 = 2ms after epoch
        $_SERVER[$headerName] = $headerValue;

        // 0.005 = 5ms after epoch
        $request = new Request($requestStartTimeSeconds);
        $request->stop(null, $currentTimeSeconds);

        $f = $request->jsonSerialize();

        $foundTag = false;
        foreach ($f['BatchCommand']['commands'] as $command) {
            if (! array_key_exists('TagRequest', $command) || $command['TagRequest']['tag'] !== 'scout.queue_time_ns') {
                continue;
            }

            self::assertSame($expectedQueueTimeNanoseconds, $command['TagRequest']['value']);
            $foundTag = true;
        }

        self::assertTrue($foundTag, 'Could not find queue time tag');

        unset($_SERVER[$headerName]);
    }
}
