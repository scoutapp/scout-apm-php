<?php

declare(strict_types=1);

namespace Scoutapm\Laravel\Router;

use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;

use function array_combine;
use function array_filter;
use function array_map;
use function count;
use function get_class;
use function implode;
use function reset;
use function spl_object_id;

/** @internal */
final class RuntimeDetermineControllerNameStrategy implements AutomaticallyDetermineControllerName
{
    private const UNKNOWN_CONTROLLER_NAME = 'Controller/unknown';

    /** @var LoggerInterface */
    private $logger;
    /** @var non-empty-list<AutomaticallyDetermineControllerName> */
    private $strategies;

    /** @param non-empty-list<AutomaticallyDetermineControllerName> $possibleStrategies */
    public function __construct(LoggerInterface $logger, array $possibleStrategies)
    {
        $this->strategies = $possibleStrategies;
        $this->logger     = $logger;
    }

    public function __invoke(Request $request): string
    {
        $validResolvedControllerNames = array_filter(
            array_combine(
                array_map(
                    static function (AutomaticallyDetermineControllerName $strategy): string {
                        // spl_object_id is appended to support adding multiple implementations from the same
                        // definition, for example an anonymous class generator.
                        return get_class($strategy) . '#' . spl_object_id($strategy);
                    },
                    $this->strategies
                ),
                array_map(
                    static function (AutomaticallyDetermineControllerName $strategy) use ($request): string {
                        return $strategy($request);
                    },
                    $this->strategies
                )
            ),
            static function (string $resolvedControllerName): bool {
                return $resolvedControllerName !== self::UNKNOWN_CONTROLLER_NAME;
            }
        );

        if (! $validResolvedControllerNames) {
            return self::UNKNOWN_CONTROLLER_NAME;
        }

        if (count($validResolvedControllerNames) > 1) {
            $this->logger->debug(
                'Multiple strategies determined the controller name, first is picked ' . implode(',', $validResolvedControllerNames),
                ['resolvedControllerNames' => $validResolvedControllerNames]
            );
        }

        return reset($validResolvedControllerNames);
    }
}
