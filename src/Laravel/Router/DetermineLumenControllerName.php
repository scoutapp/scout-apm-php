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
use function sprintf;
use function trim;

/**
 * @internal
 *
 * @psalm-type LumenRouterActionShape array{'as'?: string, uses?: string, 0?: ?Closure}
 */
final class DetermineLumenControllerName implements AutomaticallyDetermineControllerName
{
    private const CONTROLLER_PREFIX = 'Controller/';

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
            $route = $request->getMethod() . '/' . trim($request->getPathInfo(), '/');

            /**
             * @psalm-var array<
             *      string,
             *      array{
             *          method: string,
             *          uri: string,
             *          action: LumenRouterActionShape
             *      }
             * > $lumenRouteConfiguration
             */
            $lumenRouteConfiguration = $this->router->getRoutes();

            if (array_key_exists($route, $lumenRouteConfiguration)) {
                $matchedRoute = $lumenRouteConfiguration[$route];

                if (array_key_exists('action', $matchedRoute)) {
                    $matchedRouteAction = $matchedRoute['action'];

                    if (array_key_exists('as', $matchedRouteAction)) {
                        return self::CONTROLLER_PREFIX . $matchedRouteAction['as'];
                    }

                    if (array_key_exists('uses', $matchedRouteAction)) {
                        return self::CONTROLLER_PREFIX . $matchedRouteAction['uses'];
                    }

                    if (array_key_exists(0, $matchedRouteAction) && $matchedRouteAction[0] instanceof Closure) {
                        $function = new ReflectionFunction($matchedRouteAction[0]);

                        return self::CONTROLLER_PREFIX . sprintf(
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

        return self::CONTROLLER_PREFIX . $name;
    }
}
