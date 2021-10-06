<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events\Span;

use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Scoutapm\Config;
use Scoutapm\Connector\CommandWithChildren;
use Scoutapm\Events\Request\Request;
use Scoutapm\Events\Request\RequestId;
use Scoutapm\Events\Span\Span;
use Scoutapm\Helper\FindRequestHeaders\FindRequestHeaders;

/** @covers \Scoutapm\Events\Span\Span */
final class SpanTest extends TestCase
{
    /** @var CommandWithChildren&MockObject */
    private $mockParent;

    public function setUp(): void
    {
        parent::setUp();

        $this->mockParent = $this->createMock(CommandWithChildren::class);
    }

    /** @throws Exception */
    public function testCanBeInitialized(): void
    {
        $span = new Span($this->mockParent, 'name', RequestId::new());
        self::assertNull($span->getStopTime());
    }

    public function testThatASpanKnowsItIsALeafNode(): void
    {
        $span = new Span($this->mockParent, 'name', RequestId::new(), null, true);
        self::assertTrue($span->isLeaf());
    }

    /** @throws Exception */
    public function testCanBeStopped(): void
    {
        $span = new Span($this->mockParent, '', RequestId::new());
        $span->stop();
        self::assertNotNull($span->getStopTime());
    }

    /** @throws Exception */
    public function testJsonSerializes(): void
    {
        $span = new Span($this->mockParent, '', RequestId::new());
        $span->tag('Foo', 'Bar');
        $span->stop();

        $serialized = $span->jsonSerialize();

        self::assertArrayHasKey('StartSpan', $serialized[0]);
        self::assertArrayHasKey('TagSpan', $serialized[1]);
        self::assertArrayHasKey('StopSpan', $serialized[2]);
    }

    /** @throws Exception */
    public function testSpansCanBeCounted(): void
    {
        $span = new Span($this->mockParent, '', RequestId::new());
        $span->tag('Foo', 'Bar');
        $span->appendChild(new Span($this->mockParent, '', RequestId::new()));
        $span->stop();

        self::assertSame(1, $span->collectedSpans());
    }

    /** @throws Exception */
    public function testSpanNameOverride(): void
    {
        $span = new Span($this->mockParent, 'original', RequestId::new());
        self::assertSame('original', $span->getName());

        $span->updateName('fromRequest');
        self::assertSame('fromRequest', $span->getName());
    }

    /**
     * @return int[][]|string[][]
     *
     * @psalm-return list<array{spanName: string, startTime: float, endTime: float, expectedTagCount: int}>
     */
    public function spansForStackTraceProvider(): array
    {
        return [
            [
                'spanName' => 'Foo/Bar',
                'startTime' => 1.0,
                'endTime' => 1.0,
                'expectedTagCount' => 0,
            ],
            [
                'spanName' => 'Foo/Bar',
                'startTime' => 1.0,
                'endTime' => 2.0,
                'expectedTagCount' => 1,
            ],
            [
                'spanName' => 'Controller/Foo',
                'startTime' => 1.0,
                'endTime' => 2.0,
                'expectedTagCount' => 0,
            ],
            [
                'spanName' => 'Middleware/Foo',
                'startTime' => 1.0,
                'endTime' => 2.0,
                'expectedTagCount' => 0,
            ],
            [
                'spanName' => 'Job/Foo',
                'startTime' => 1.0,
                'endTime' => 2.0,
                'expectedTagCount' => 0,
            ],
        ];
    }

    /**
     * @throws Exception
     *
     * @dataProvider spansForStackTraceProvider
     */
    public function testStackTracesAreOnlyAddedForAppropriateSpans(string $spanName, float $startTime, float $endTime, int $expectedTagCount): void
    {
        $request = Request::fromConfigAndOverrideTime(Config::fromArray([]), $this->createMock(FindRequestHeaders::class));

        $span = new Span($request, $spanName, RequestId::new(), $startTime);
        $span->stop($endTime);

        /** @psalm-suppress DeprecatedMethod */
        self::assertCount($expectedTagCount, $span->getTags());
    }
}
