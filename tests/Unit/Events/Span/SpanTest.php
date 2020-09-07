<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events\Span;

use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Scoutapm\Connector\CommandWithChildren;
use Scoutapm\Events\Request\Request;
use Scoutapm\Events\Request\RequestId;
use Scoutapm\Events\Span\Span;

/** @covers \Scoutapm\Events\Span\Span */
final class SpanTest extends TestCase
{
    /** @var CommandWithChildren&MockObject */
    private $mockParent;

    public function setUp() : void
    {
        parent::setUp();

        $this->mockParent = $this->createMock(CommandWithChildren::class);
    }

    /** @throws Exception */
    public function testCanBeInitialized() : void
    {
        $span = new Span($this->mockParent, 'name', RequestId::new());
        self::assertNotNull($span);
    }

    /** @throws Exception */
    public function testCanBeStopped() : void
    {
        $span = new Span($this->mockParent, '', RequestId::new());
        $span->stop();
        self::assertNotNull($span);
    }

    /** @throws Exception */
    public function testJsonSerializes() : void
    {
        $span = new Span($this->mockParent, '', RequestId::new());
        $span->tag('Foo', 'Bar');
        $span->stop();

        $serialized = $span->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('StartSpan', $serialized[0]);
        self::assertArrayHasKey('TagSpan', $serialized[1]);
        self::assertArrayHasKey('StopSpan', $serialized[2]);
    }

    /** @throws Exception */
    public function testSpansCanBeCounted() : void
    {
        $span = new Span($this->mockParent, '', RequestId::new());
        $span->tag('Foo', 'Bar');
        $span->appendChild(new Span($this->mockParent, '', RequestId::new()));
        $span->stop();

        self::assertSame(1, $span->collectedSpans());
    }

    /** @throws Exception */
    public function testSpanNameOverride() : void
    {
        $span = new Span($this->mockParent, 'original', RequestId::new());
        self::assertSame('original', $span->getName());

        $span->updateName('fromRequest');
        self::assertSame('fromRequest', $span->getName());
    }

    /** @return int[][]|string[][] */
    public function spansForStackTraceProvider() : array
    {
        return [
            [
                'spanName' => 'Foo/Bar',
                'startTime' => 1,
                'endTime' => 1,
                'expectedTagCount' => 0,
            ],
            [
                'spanName' => 'Foo/Bar',
                'startTime' => 1,
                'endTime' => 2,
                'expectedTagCount' => 1,
            ],
            [
                'spanName' => 'Controller/Foo',
                'startTime' => 1,
                'endTime' => 2,
                'expectedTagCount' => 0,
            ],
            [
                'spanName' => 'Middleware/Foo',
                'startTime' => 1,
                'endTime' => 2,
                'expectedTagCount' => 0,
            ],
            [
                'spanName' => 'Job/Foo',
                'startTime' => 1,
                'endTime' => 2,
                'expectedTagCount' => 0,
            ],
        ];
    }

    /**
     * @throws Exception
     *
     * @dataProvider spansForStackTraceProvider
     */
    public function testStackTracesAreOnlyAddedForAppropriateSpans(string $spanName, float $startTime, float $endTime, int $expectedTagCount) : void
    {
        $request = new Request();

        $span = new Span($request, $spanName, RequestId::new(), $startTime);
        $span->stop($endTime);

        self::assertCount($expectedTagCount, $span->getTags());
    }
}
