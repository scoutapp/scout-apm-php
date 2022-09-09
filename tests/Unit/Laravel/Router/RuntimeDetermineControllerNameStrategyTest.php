<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Laravel\Router;

use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Scoutapm\Laravel\Router\AutomaticallyDetermineControllerName;
use Scoutapm\Laravel\Router\RuntimeDetermineControllerNameStrategy;
use Scoutapm\UnitTests\TestLogger;

/** @covers \Scoutapm\Laravel\Router\RuntimeDetermineControllerNameStrategy */
final class RuntimeDetermineControllerNameStrategyTest extends TestCase
{
    /** @var TestLogger */
    private $logger;
    /** @var Request */
    private $request;

    public function setUp(): void
    {
        parent::setUp();

        $this->logger  = new TestLogger();
        $this->request = new Request();
    }

    private function anonymousImplementationThatReturns(string $controllerName): AutomaticallyDetermineControllerName
    {
        return new class ($controllerName) implements AutomaticallyDetermineControllerName {
            /** @var string */
            private $controllerName;

            public function __construct(string $controllerName)
            {
                $this->controllerName = $controllerName;
            }

            public function __invoke(Request $request): string
            {
                return $this->controllerName;
            }
        };
    }

    public function testSingleStrategyReturnsControllerName(): void
    {
        self::assertSame(
            'Controller/MatchedName',
            (new RuntimeDetermineControllerNameStrategy(
                $this->logger,
                [
                    $this->anonymousImplementationThatReturns('Controller/MatchedName'),
                ]
            ))($this->request)
        );
    }

    public function testMultipleStrategiesWithOneMatchReturnsControllerName(): void
    {
        self::assertSame(
            'Controller/MatchedName',
            (new RuntimeDetermineControllerNameStrategy(
                $this->logger,
                [
                    $this->anonymousImplementationThatReturns('Controller/unknown'),
                    $this->anonymousImplementationThatReturns('Controller/MatchedName'),
                    $this->anonymousImplementationThatReturns('Controller/unknown'),
                ]
            ))($this->request)
        );
    }

    public function testMultipleStrategiesMatchingReturnsFirst(): void
    {
        self::assertSame(
            'Controller/MatchedName1',
            (new RuntimeDetermineControllerNameStrategy(
                $this->logger,
                [
                    $this->anonymousImplementationThatReturns('Controller/MatchedName1'),
                    $this->anonymousImplementationThatReturns('Controller/MatchedName2'),
                ]
            ))($this->request)
        );
        $this->logger->hasDebugThatContains('Multiple strategies determined the controller name, first is picked');
    }

    public function testNoMatchingStrategiesReturnsUnknown(): void
    {
        self::assertSame(
            'Controller/unknown',
            (new RuntimeDetermineControllerNameStrategy(
                $this->logger,
                [
                    $this->anonymousImplementationThatReturns('Controller/unknown'),
                    $this->anonymousImplementationThatReturns('Controller/unknown'),
                ]
            ))($this->request)
        );
    }
}
