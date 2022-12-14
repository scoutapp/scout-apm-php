<?php

declare(strict_types=1);

namespace Scoutapm\ScoutApmBundle\Twig;

use Closure;
use Scoutapm\ScoutApmAgent;
use Twig\Environment as Twig;
use Twig\TemplateWrapper;

/** @internal This class extends a third party vendor, so we mark as internal to not expose upstream BC breaks */
class TwigDecorator extends Twig
{
    use TwigMethods;

    /** @var Twig */
    private $twig;
    /** @var ScoutApmAgent */
    private $agent;

    public function __construct(Twig $twig, ScoutApmAgent $agent)
    {
        $this->twig  = $twig;
        $this->agent = $agent;
    }

    /** @param string|TemplateWrapper $nameOrTemplateWrapper */
    private function nameOrConvertTemplateWrapperToString($nameOrTemplateWrapper): string
    {
        if (! $nameOrTemplateWrapper instanceof TemplateWrapper) {
            return $nameOrTemplateWrapper;
        }

        return $nameOrTemplateWrapper->getTemplateName();
    }

    /** @return mixed */
    private function instrument(string $name, Closure $callable)
    {
        return $this->agent->instrument(
            'View',
            $name,
            $callable
        );
    }

    /** {@inheritDoc} */
    public function render($name, array $context = []): string
    {
        return $this->instrument(
            $this->nameOrConvertTemplateWrapperToString($name),
            function () use ($name, $context) {
                return $this->twig->render($name, $context);
            }
        );
    }

    /** {@inheritDoc} */
    public function display($name, array $context = []): void
    {
        $this->instrument(
            $this->nameOrConvertTemplateWrapperToString($name),
            function () use ($name, $context): void {
                $this->twig->display($name, $context);
            }
        );
    }
}
