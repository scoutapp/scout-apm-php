<?php

declare(strict_types=1);

namespace Scoutapm\Laravel\Router;

use Closure;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Router;
use ReflectionFunction;
use Scoutapm\Logger\FilteredLogLevelDecorator;
use Throwable;

use function array_key_exists;
use function basename;
use function count;
use function get_class;
use function is_array;
use function is_callable;
use function is_object;
use function is_string;
use function sprintf;
use function trim;

final class DetermineLumenControllerName implements AutomaticallyDetermineControllerName
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

    // @todo needs tests
    public function __invoke(Request $request): string
    {
        $name = 'unknown';

        try {
            $route = $request->getMethod() . '/' . trim($request->getPathInfo(), '/');

            $lumenRouteConfiguration = $this->router->getRoutes();

            if (array_key_exists($route, $lumenRouteConfiguration)) {
                $matchedRoute = $lumenRouteConfiguration[$route];

                if (array_key_exists('action', $matchedRoute)) {
                    $matchedRouteAction = $matchedRoute['action'];

                    if (array_key_exists('as', $matchedRouteAction)) {
                        $name = $matchedRouteAction['as'];
                    }

                    if (array_key_exists('uses', $matchedRouteAction)) {
                        $name = $matchedRouteAction['uses'];
                    }

                    if (
                        is_callable($matchedRouteAction)
                        || (is_array($matchedRouteAction) && count($matchedRouteAction) === 2)
                    ) {
                        if (is_string($matchedRouteAction)) {
                            $name = $matchedRouteAction;
                        }

                        if (is_array($matchedRoute['action'])) {
                            $name = sprintf(
                                '%s@%s',
                                is_object($matchedRouteAction[0]) ? get_class($matchedRouteAction[0]) : trim($matchedRouteAction[0]),
                                trim($matchedRouteAction[1])
                            );
                        }
                    }

                    if (is_array($matchedRouteAction) && array_key_exists(0, $matchedRouteAction) && $matchedRouteAction[0] instanceof Closure) {
                        $function = new ReflectionFunction($matchedRouteAction[0]);
                        $name     = sprintf(
                            'closure_%s@%d',
                            basename($function->getFileName()),
                            $function->getStartLine()
                        );
                    }
                }
            }
        } catch (Throwable $e) {
            $this->logger->debug(
                'Exception obtaining name of Lumen endpoint: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }

        return 'Controller/' . $name;
    }
}
