<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\ScoutApmBundle\Twig;

use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use Scoutapm\ScoutApmAgent;
use Scoutapm\ScoutApmBundle\Twig\TwigDecorator;
use stdClass;
use Twig\Compiler;
use Twig\Environment as Twig;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extension\ExtensionInterface;
use Twig\Lexer;
use Twig\Loader\LoaderInterface;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\Parser;
use Twig\RuntimeLoader\RuntimeLoaderInterface;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;
use Twig\TokenParser\TokenParserInterface;
use Twig\TokenStream;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

use function assert;
use function sprintf;
use function uniqid;

/** @covers \Scoutapm\ScoutApmBundle\Twig\TwigDecorator */
final class TwigDecoratorTest extends TestCase
{
    /** @var Twig&MockObject */
    private $twig;
    /** @var ScoutApmAgent&MockObject */
    private $agent;
    /** @var TwigDecorator */
    private $twigDecorator;

    public function setUp(): void
    {
        parent::setUp();

        $this->twig  = $this->createMock(Twig::class);
        $this->agent = $this->createMock(ScoutApmAgent::class);

        $this->twigDecorator = new TwigDecorator($this->twig, $this->agent);
    }

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function testInstrumentationIsPerformedOnRenderAndValueIsReturned(): void
    {
        $renderedContent = uniqid('renderedContent', true);
        $templateName    = uniqid('foo.html.twig', true);

        $this->agent
            ->expects(self::once())
            ->method('instrument')
            ->with(
                'View',
                $templateName,
                self::isType(IsType::TYPE_CALLABLE)
            )
            ->willReturnCallback(
                /** @return mixed */
                static function (string $type, string $name, callable $callback) {
                    return $callback();
                }
            );

        $this->twig
            ->expects(self::once())
            ->method('render')
            ->with($templateName, ['a' => 'a', 'b' => 'b'])
            ->willReturn($renderedContent);

        self::assertSame($renderedContent, $this->twigDecorator->render($templateName, ['a' => 'a', 'b' => 'b']));
    }

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function testInstrumentationIsPerformedOnDisplay(): void
    {
        $templateName = uniqid('foo.html.twig', true);

        $this->agent
            ->expects(self::once())
            ->method('instrument')
            ->with(
                'View',
                $templateName,
                self::isType(IsType::TYPE_CALLABLE)
            )
            ->willReturnCallback(
                /** @return mixed */
                static function (string $type, string $name, callable $callback) {
                    return $callback();
                }
            );

        $this->twig
            ->expects(self::once())
            ->method('display')
            ->with($templateName, ['a' => 'a', 'b' => 'b']);

        $this->twigDecorator->display($templateName, ['a' => 'a', 'b' => 'b']);
    }

    public function testTemplateWrapperIsConvertedToStringForInstrumentation(): void
    {
        $templateName = uniqid('foo.html.twig', true);
        $template     = $this->createMock(Template::class);
        /** @psalm-suppress InternalMethod This might break indeed, but we don't want the whole of Twig here */
        $templateWrapper = new TemplateWrapper($this->twig, $template);
        $renderedContent = uniqid('renderedContent', true);

        $template
            ->expects(self::once())
            ->method('getTemplateName')
            ->willReturn($templateName);

        $this->agent
            ->expects(self::once())
            ->method('instrument')
            ->with(
                'View',
                $templateName,
                self::isType(IsType::TYPE_CALLABLE)
            )
            ->willReturnCallback(
                /** @return mixed */
                static function (string $type, string $name, callable $callback) {
                    return $callback();
                }
            );

        $this->twig
            ->expects(self::once())
            ->method('render')
            ->with($templateWrapper, ['a' => 'a', 'b' => 'b'])
            ->willReturn($renderedContent);

        self::assertSame($renderedContent, $this->twigDecorator->render($templateWrapper, ['a' => 'a', 'b' => 'b']));
    }

    /**
     * @return mixed[][]
     * @psalm-return array<int, array{0: string, 1: mixed, 2: mixed[]}>
     */
    public function decoratedTwigMethodsProvider(): array
    {
        $moduleNode = new ModuleNode(
            $this->createMock(Node::class),
            $this->createMock(AbstractExpression::class),
            $this->createMock(Node::class),
            $this->createMock(Node::class),
            $this->createMock(Node::class),
            'a',
            new Source('foo', 'bar')
        );
        /** @psalm-suppress InternalMethod This might break indeed, but we don't want the whole of Twig here */
        $templateWrapper = new TemplateWrapper($this->createMock(Twig::class), $this->createMock(Template::class));

        // phpcs:disable Squiz.Arrays.ArrayDeclaration.ValueNoNewline
        // phpcs:disable SlevomatCodingStandard.ControlStructures.ControlStructureSpacing.IncorrectLinesCountBeforeControlStructure

        /**
         * @psalm-suppress RedundantCondition
         * @psalm-suppress TypeDoesNotContainType
         */
        return [
            ['enableDebug', null, []],
            ['disableDebug', null, []],
            ['isDebug', true, []],
            ['enableAutoReload', null, []],
            ['disableAutoReload', null, []],
            ['isAutoReload', true, []],
            ['enableStrictVariables', null, []],
            ['disableStrictVariables', null, []],
            ['isStrictVariables', true, []],
            ['getCache', 'foo', []],
            ['setCache', null, ['foo']],
            ['getTemplateClass', 'foo', ['foo', 0]],
            ['render', 'foo', ['foo', ['a' => 'a']]],
            ['display', null, ['foo', ['a' => 'a']]],
            ['load', $templateWrapper, ['foo']],
            [
                'loadTemplate',
                $this->createMock(Template::class),
                Twig::MAJOR_VERSION === 2
                    ? ['foo', 0]
                    : ['foo', 'bar', 0],
            ],
            ['createTemplate', $templateWrapper, ['foo', 'bar']],
            ['isTemplateFresh', true, ['foo', 0]],
            ['resolveTemplate', $templateWrapper, ['foo']],
            ['setLexer', null, [$this->createMock(Lexer::class)]],
            ['tokenize', new TokenStream([]), [new Source('foo', 'bar')]],
            ['setParser', null, [$this->createMock(Parser::class)]],
            ['parse', $moduleNode, [new TokenStream([])]],
            ['setCompiler', null, [$this->createMock(Compiler::class)]],
            ['compile', 'foo', [$moduleNode]],
            ['compileSource', 'foo', [new Source('foo', 'bar')]],
            ['setLoader', null, [$this->createMock(LoaderInterface::class)]],
            ['getLoader', $this->createMock(LoaderInterface::class), []],
            ['getCharset', 'foo', []],
            ['setCharset', null, ['foo']],
            ['hasExtension', true, ['foo']],
            ['addRuntimeLoader', null, [$this->createMock(RuntimeLoaderInterface::class)]],
            ['getExtension', $this->createMock(ExtensionInterface::class), ['foo']],
            ['getRuntime', new stdClass(), ['foo']],
            ['addExtension', null, [$this->createMock(ExtensionInterface::class)]],
            ['setExtensions', null, [[$this->createMock(ExtensionInterface::class)]]],
            ['getExtensions', [$this->createMock(ExtensionInterface::class)], []],
            ['addTokenParser', null, [$this->createMock(TokenParserInterface::class)]],
            ['getTokenParsers', [$this->createMock(TokenParserInterface::class)], []],
            ['getTags', [$this->createMock(TokenParserInterface::class)], []],
            ['addNodeVisitor', null, [$this->createMock(NodeVisitorInterface::class)]],
            ['getNodeVisitors', [$this->createMock(NodeVisitorInterface::class)], []],
            ['addFilter', null, [new TwigFilter('foo')]],
            ['getFilter', new TwigFilter('foo'), ['foo']],
            [
                'registerUndefinedFilterCallback',
                null,
                [static function (): void {
                },
                ],
            ],
            ['getFilters', [new TwigFilter('foo')], []],
            ['addTest', null, [new TwigTest('foo')]],
            ['getTests', [new TwigTest('foo')], []],
            ['getTest', new TwigTest('foo'), ['foo']],
            ['addFunction', null, [new TwigFunction('foo')]],
            ['getFunction', new TwigFunction('foo'), ['foo']],
            [
                'registerUndefinedFunctionCallback',
                null,
                [static function (): void {
                },
                ],
            ],
            ['getFunctions', [new TwigFunction('foo')], []],
            ['addGlobal', null, ['foo', 'bar']],
            ['getGlobals', ['foo' => 'bar'], []],
            ['mergeGlobals', ['foo' => 'bar', 'a' => 'b'], [['a' => 'b']]],
            ['getUnaryOperators', ['a', 'b'], []],
            ['getBinaryOperators', ['a', 'b'], []],
        ];
    }

    // phpcs:enable

    /**
     * @param mixed   $returnValue
     * @param mixed[] $args
     *
     * @dataProvider decoratedTwigMethodsProvider
     */
    public function testAllMethodsAreProxiedToOriginalTwig(string $methodName, $returnValue, array $args): void
    {
        $twigClassReflection = new ReflectionClass(Twig::class);

        if (! $twigClassReflection->hasMethod($methodName)) {
            self::markTestSkipped(sprintf(
                'Installed Symfony version does not have method: %s#%s()',
                $twigClassReflection->getName(),
                $methodName
            ));
        }

        $this->agent
            ->method('instrument')
            ->willReturnCallback(
                /** @return mixed */
                static function (string $type, string $name, callable $callback) {
                    return $callback();
                }
            );

        if ($this->methodIsVoid($twigClassReflection, $methodName)) {
            $this->twig
                ->expects(self::once())
                ->method($methodName)
                ->with(...$args);
        } else {
            $this->twig
                ->expects(self::once())
                ->method($methodName)
                ->with(...$args)
                ->willReturn($returnValue);
        }

        self::assertSame($returnValue, $this->twigDecorator->{$methodName}(...$args));
    }

    private function methodIsVoid(ReflectionClass $reflectionClass, string $methodName): bool
    {
        $methodReflection = $reflectionClass->getMethod($methodName);

        if (! $methodReflection->hasReturnType()) {
            return false;
        }

        $returnType = $methodReflection->getReturnType();

        assert($returnType !== null);

        if ($returnType instanceof ReflectionNamedType) {
            return $returnType->getName() === 'void';
        }

        return $returnType->__toString() === 'void';
    }
}
