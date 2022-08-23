<?php

declare(strict_types=1);

namespace Scoutapm\Laravel\Middleware;

use Closure;
use Illuminate\Http\Request;
use Scoutapm\Events\Span\SpanReference;
use Scoutapm\Laravel\Router\AutomaticallyDetermineControllerName;
use Scoutapm\Logger\FilteredLogLevelDecorator;
use Scoutapm\ScoutApmAgent;
use Throwable;

final class ActionInstrument
{
    /** @var ScoutApmAgent */
    private $agent;
    /** @var FilteredLogLevelDecorator */
    private $logger;
    /** @var AutomaticallyDetermineControllerName */
    private $determineControllerName;

    public function __construct(
        ScoutApmAgent $agent,
        FilteredLogLevelDecorator $logger,
        AutomaticallyDetermineControllerName $determineControllerName
    ) {
        $this->agent                   = $agent;
        $this->logger                  = $logger;
        $this->determineControllerName = $determineControllerName;
    }

    /**
     * @psalm-param Closure(Request):mixed $next
     *
     * @return mixed
     *
     * @throws Throwable
     */
    public function handle(Request $request, Closure $next)
    {
        $this->logger->debug('Handle ActionInstrument');

        return $this->agent->webTransaction(
            'unknown',
            /** @return mixed */
            function (?SpanReference $span) use ($request, $next) {
                try {
                    /** @var mixed $response */
                    $response = $next($request);
                } catch (Throwable $e) {
                    $this->agent->tagRequest('error', 'true');

                    throw $e;
                }

                if ($span !== null) {
                    $determineControllerName = $this->determineControllerName;
                    $span->updateName($determineControllerName($request));
                }

                return $response;
            }
        );
    }
}
