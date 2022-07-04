<?php

declare(strict_types=1);

namespace Scoutapm\Laravel\View\Engine;

use Illuminate\Contracts\View\Engine;
use Illuminate\View\Compilers\CompilerInterface;
use Illuminate\View\Factory;
use Scoutapm\ScoutApmAgent;

use function assert;
use function method_exists;

/** @noinspection ContractViolationInspection */
final class ScoutViewEngineDecorator implements Engine
{
    public const VIEW_FACTORY_SHARED_KEY = '__scout_apm_view_name';

    /**
     * Note: property MUST be called `$engine` as package `spatie/laravel-ignition` makes assumptions about how
     * implementors of {@see Engine} structure their classes.
     *
     * @link https://github.com/spatie/laravel-ignition/blob/d53075177ee0c710fbf588b8569f50435e1da054/src/Views/ViewExceptionMapper.php#L124-L130
     *
     * @var Engine
     */
    private $engine;

    /** @var ScoutApmAgent */
    private $agent;

    /** @var Factory */
    private $viewFactory;

    public function __construct(Engine $engine, ScoutApmAgent $agent, Factory $viewFactory)
    {
        $this->engine      = $engine;
        $this->agent       = $agent;
        $this->viewFactory = $viewFactory;
    }

    /**
     * {@inheritDoc}
     */
    public function get($path, array $data = []): string
    {
        return $this->agent->instrument(
            'View',
            (string) $this->viewFactory->shared(self::VIEW_FACTORY_SHARED_KEY, 'unknown'),
            function () use ($path, $data) {
                return $this->engine->get($path, $data);
            }
        );
    }

    /**
     * Since Laravel has a nasty habit of exposing public API that is not defined in interfaces, we must expose the
     * getCompiler method commonly used in the actual view engines.
     *
     * Unfortunately, we have to make all kinds of assertions due to this violation :/
     */
    public function getCompiler(): CompilerInterface
    {
        assert(method_exists($this->engine, 'getCompiler'));
        /** @psalm-suppress UndefinedInterfaceMethod */
        $compiler = $this->engine->getCompiler();
        assert($compiler instanceof CompilerInterface);

        return $compiler;
    }
}
