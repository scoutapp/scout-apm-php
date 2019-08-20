<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events;

use PHPUnit\Framework\TestCase;
use Scoutapm\Events\Request\Request;
use function next;
use function reset;

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
        $request->stop();
        self::assertNotNull($request);
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
        self::assertArrayHasKey('StartRequest', reset($serialized));
        self::assertArrayHasKey('RequestTag', next($serialized));

        self::assertArrayHasKey('StartSpan', next($serialized));
        self::assertArrayHasKey('TagSpan', next($serialized));
        self::assertArrayHasKey('StopSpan', next($serialized));

        self::assertArrayHasKey('FinishRequest', next($serialized));
    }
}
