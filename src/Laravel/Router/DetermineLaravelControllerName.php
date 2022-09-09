<?php

declare(strict_types=1);

namespace Scoutapm\Laravel\Router;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Scoutapm\Logger\FilteredLogLevelDecorator;
use Throwable;
use Webmozart\Assert\Assert;

/** @internal */
final class DetermineLaravelControllerName implements AutomaticallyDetermineControllerName
{
    /** @var FilteredLogLevelDecorator */
    private $logger;
    /** @var Router */
    private $router;

    public function __construct(FilteredLogLevelDecorator $logger, Router $router)
    {
        $this->logger = $logger;
        $this->router = $router;
    }

    public function __invoke(Request $request): string
    {
        $name = 'unknown';

        try {
            $route = $this->router->current();
            if ($route !== null) {
                /** @var mixed $name */
                $name = $route->action['controller'] ?? $route->uri();
                Assert::stringNotEmpty($name);
            }
        } catch (Throwable $e) {
            $this->logger->debug(
                'Exception obtaining name of Laravel endpoint: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }

        return 'Controller/' . $name;
    }
}
