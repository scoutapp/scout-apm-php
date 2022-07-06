<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events\Span;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Scoutapm\Events\Span\Span;
use Scoutapm\Events\Span\SpanReference;

/** @covers \Scoutapm\Events\Span\SpanReference */
final class SpanReferenceTest extends TestCase
{
    /** @var Span&MockObject */
    private $mockSpan;

    /** @var SpanReference */
    private $spanReference;

    public function setUp(): void
    {
        parent::setUp();

        $this->mockSpan = $this->createMock(Span::class);

        $this->spanReference = SpanReference::fromSpan($this->mockSpan);
    }

    /**
     * @return string[][]|string[][][]|null[][]|float[][]
     * @psalm-return array<string, array{methodName: string, params: list<string>, expectedReturn: (string|float|null)}>
     */
    public function proxiedMethodsProvider(): array
    {
        return [
            'updateName' => [
                'methodName' => 'updateName',
                'params' => ['newName'],
                'expectedReturn' => null,
            ],
            'tag' => [
                'methodName' => 'tag',
                'params' => ['tagName', 'tagValue'],
                'expectedReturn' => null,
            ],
            'getName' => [
                'methodName' => 'getName',
                'params' => [],
                'expectedReturn' => 'spanName',
            ],
            'getStartTime-null' => [
                'methodName' => 'getStartTime',
                'params' => [],
                'expectedReturn' => null,
            ],
            'getStartTime-string' => [
                'methodName' => 'getStartTime',
                'params' => [],
                'expectedReturn' => 'startTime',
            ],
            'getStopTime-null' => [
                'methodName' => 'getStopTime',
                'params' => [],
                'expectedReturn' => null,
            ],
            'getStopTime-string' => [
                'methodName' => 'getStopTime',
                'params' => [],
                'expectedReturn' => 'stopTime',
            ],
            'duration-null' => [
                'methodName' => 'duration',
                'params' => [],
                'expectedReturn' => null,
            ],
            'duration-float' => [
                'methodName' => 'duration',
                'params' => [],
                'expectedReturn' => 1.2345,
            ],
        ];
    }

    /**
     * @param mixed[] $params
     * @param mixed   $expectedReturn
     *
     * @dataProvider proxiedMethodsProvider
     */
    public function testMethodsAreProxiedToMockSpan(string $methodName, array $params, $expectedReturn): void
    {
        $mock = $this->mockSpan->expects(self::once())
            ->method($methodName)
            ->with(...$params);

        if ($expectedReturn !== null) {
            $mock->willReturn($expectedReturn);
        }

        $actualReturn = $this->spanReference->$methodName(...$params);

        self::assertSame($expectedReturn, $actualReturn);
    }
}
