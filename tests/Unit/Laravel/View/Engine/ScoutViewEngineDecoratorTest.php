<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Laravel\View\Engine;

use Illuminate\Container\Container as ContainerSingleton;
use Illuminate\Contracts\Container\Container as ContainerInterface;
use Illuminate\Contracts\View\Engine;
use Illuminate\View\Compilers\CompilerInterface;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory as ViewFactory;
use Illuminate\View\ViewException;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Scoutapm\Laravel\View\Engine\ScoutViewEngineDecorator;
use Scoutapm\ScoutApmAgent;
use Spatie\LaravelIgnition\Views\BladeSourceMapCompiler;
use Spatie\LaravelIgnition\Views\ViewExceptionMapper;

use function class_exists;
use function get_class;
use function method_exists;
use function sprintf;
use function uniqid;

/** @covers \Scoutapm\Laravel\View\Engine\ScoutViewEngineDecorator */
final class ScoutViewEngineDecoratorTest extends TestCase
{
    /** @var Engine&MockObject */
    private $realEngine;

    /** @var ScoutApmAgent&MockObject */
    private $agent;

    /** @var ViewFactory&MockObject */
    private $viewFactory;

    /** @var ScoutViewEngineDecorator */
    private $viewEngineDecorator;

    public function setUp(): void
    {
        parent::setUp();

        // Note: getCompiler is NOT a real method, it is implemented by the real implementation only, SOLID violation in Laravel
        $this->realEngine  = $this->createMock(EngineImplementationWithGetCompilerMethod::class);
        $this->agent       = $this->createMock(ScoutApmAgent::class);
        $this->viewFactory = $this->createMock(ViewFactory::class);

        $this->viewEngineDecorator = new ScoutViewEngineDecorator($this->realEngine, $this->agent, $this->viewFactory);
    }

    public function testGetWrapsCallToRealEngineInInstrumentation(): void
    {
        $viewTemplateName = uniqid('viewTemplateName', true);
        $path             = uniqid('path', true);
        $data             = ['foo' => 'bar'];
        $renderedString   = uniqid('renderedString', true);

        $this->viewFactory->expects(self::once())
            ->method('shared')
            ->with(ScoutViewEngineDecorator::VIEW_FACTORY_SHARED_KEY, 'unknown')
            ->willReturn($viewTemplateName);

        $this->agent
            ->expects(self::once())
            ->method('instrument')
            ->with('View', $viewTemplateName, self::isType(IsType::TYPE_CALLABLE))
            ->willReturnCallback(
                /** @return mixed */
                static function (string $type, string $name, callable $transaction) {
                    return $transaction();
                }
            );

        $this->realEngine->expects(self::once())
            ->method('get')
            ->with($path, $data)
            ->willReturn($renderedString);

        self::assertSame($renderedString, $this->viewEngineDecorator->get($path, $data));
    }

    public function testGetFallsBackToUnknownTemplateNameWhenPathWasNotDefined(): void
    {
        $path           = uniqid('path', true);
        $data           = ['foo' => 'bar'];
        $renderedString = uniqid('renderedString', true);

        $this->viewFactory->expects(self::once())
            ->method('shared')
            ->with(ScoutViewEngineDecorator::VIEW_FACTORY_SHARED_KEY, 'unknown')
            ->willReturn('unknown');

        $this->agent
            ->expects(self::once())
            ->method('instrument')
            ->with('View', 'unknown', self::isType(IsType::TYPE_CALLABLE))
            ->willReturnCallback(
                /** @return mixed */
                static function (string $type, string $name, callable $transaction) {
                    return $transaction();
                }
            );

        $this->realEngine->expects(self::once())
            ->method('get')
            ->with($path, $data)
            ->willReturn($renderedString);

        self::assertSame($renderedString, $this->viewEngineDecorator->get($path, $data));
    }

    public function testGetCompilerWillProxyToRealEngineGetCompilerMethd(): void
    {
        $compiler = $this->createMock(CompilerInterface::class);

        $this->realEngine->expects(self::once())
            ->method('getCompiler')
            ->willReturn($compiler);

        self::assertSame($compiler, $this->viewEngineDecorator->getCompiler());
    }

    public function testForgetCompiledOrNotExpiredWillProxyToRealEngineForgetCompiledOrNotExpiredMethd(): void
    {
        $this->realEngine
            ->expects(self::once())
            ->method('forgetCompiledOrNotExpired');

        $this->viewEngineDecorator->forgetCompiledOrNotExpired();
    }

    /**
     * ScoutViewEngineDecorator is designed to be generic decorator for {@see \Illuminate\Contracts\View\Engine}
     * implementations. However, specific implementations such as {@see \Illuminate\View\Engines\CompilerEngine} keep
     * having public API added that is NOT part of the engine, therefore breaking LSP.
     *
     * @link https://github.com/scoutapp/scout-apm-laravel/issues/8
     * @link https://github.com/scoutapp/scout-apm-php/issues/293
     *
     * This test aims to ensure that when public methods are added to {@see \Illuminate\View\Engines\CompilerEngine}
     * then we have implemented it in {@see \Scoutapm\Laravel\View\Engine\ScoutViewEngineDecorator}.
     */
    public function testScoutViewEngineDecoratorImplementsAllPublicApiOfCompilerEngine(): void
    {
        $realCompilerEngineMethods = (new ReflectionClass(CompilerEngine::class))
            ->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($realCompilerEngineMethods as $compilerEngineMethod) {
            if ($compilerEngineMethod->isConstructor()) {
                continue;
            }

            $compilerEngineMethodName = $compilerEngineMethod->getShortName();

            self::assertTrue(
                method_exists($this->viewEngineDecorator, $compilerEngineMethodName),
                sprintf(
                    'Method "%s" did not exist on %s, but exists in %s',
                    $compilerEngineMethodName,
                    get_class($this->viewEngineDecorator),
                    CompilerEngine::class
                )
            );
        }
    }

    public function testSpatieLaravelIgnitionCompatibility(): void
    {
        if (! class_exists(ViewExceptionMapper::class)) {
            self::markTestSkipped('Test depends on `spatie/laravel-ignition`, but it is not installed');
        }

        /**
         * The `spatie/laravel-ignition` package depends on the engine having a property called `lastCompiled`, which
         * only exists in the `\Illuminate\View\Engines\CompilerEngine` Blade Compiler. The implementation does sort of
         * account for decoration, but it expects the property to be called `engine`. Therefore, in this test, we
         * invoke the problematic consumer to ensure our decorated view engine conforms to this assumption.
         *
         * @link https://github.com/spatie/laravel-ignition/blob/d53075177ee0c710fbf588b8569f50435e1da054/src/Views/ViewExceptionMapper.php#L124-L130
         *
         * @noinspection PhpPossiblePolymorphicInvocationInspection PhpUndefinedFieldInspection
         * @psalm-suppress NoInterfaceProperties
         */
        $this->realEngine->lastCompiled = [];

        $viewEngineResolver = new EngineResolver();
        $viewEngineResolver->register('blade', function () {
            return $this->viewEngineDecorator;
        });

        $fakeContainer = $this->createMock(ContainerInterface::class);
        $fakeContainer->expects(self::once())
            ->method('make')
            ->with('view.engine.resolver')
            ->willReturn($viewEngineResolver);
        ContainerSingleton::setInstance($fakeContainer);

        $vem = new ViewExceptionMapper($this->createMock(BladeSourceMapCompiler::class));
        $vem->map(new ViewException('things (View: paththing)'));
    }
}
