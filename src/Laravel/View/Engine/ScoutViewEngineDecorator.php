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
     * Note: this is a proxy for a method that does NOT exist in {@see \Illuminate\Contracts\View\Engine} but is still
     * relied on by consumers of {@see \Illuminate\View\Engines\CompilerEngine::getCompiler}
     *
     * @link https://github.com/scoutapp/scout-apm-laravel/issues/8
     */
    public function getCompiler(): CompilerInterface
    {
        // Assertion is necessary as normally this breaks LSP
        assert(method_exists($this->engine, 'getCompiler'));
        $compiler = $this->engine->getCompiler();
        assert($compiler instanceof CompilerInterface);

        return $compiler;
    }

    /**
     * Note: this is a proxy for a method that does NOT exist in {@see \Illuminate\Contracts\View\Engine} but is still
     * relied on by consumers of {@see \Illuminate\View\Engines\CompilerEngine::forgetCompiledOrNotExpired}
     *
     * @link https://github.com/scoutapp/scout-apm-php/issues/293
     */
    public function forgetCompiledOrNotExpired(): void
    {
        // Assertion is necessary as normally this breaks LSP
        assert(method_exists($this->engine, 'forgetCompiledOrNotExpired'));
        $this->engine->forgetCompiledOrNotExpired();
    }
}
