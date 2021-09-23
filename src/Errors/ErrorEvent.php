<?php

declare(strict_types=1);

namespace Scoutapm\Errors;

use Scoutapm\Config;
use Scoutapm\Events\Request\Request;
use Scoutapm\Helper\FilterParameters;
use Throwable;

use function array_key_exists;
use function array_map;
use function array_values;
use function get_class;
use function sprintf;
use function str_replace;

/**
 * @internal This is not covered by BC promise
 *
 * @psalm-type ErrorEventJsonableArray = array{
 *      exception_class: class-string,
 *      message: non-empty-string,
 *      request_id: string|null,
 *      request_uri: non-empty-string,
 *      request_params?: array<string,string|array<array-key,string|array<array-key,string|array<array-key,string>>>>,
 *      request_session?: array<string,string>,
 *      environment?: array<string,string>,
 *      trace: list<string>,
 *      request_components?: array{
 *          module: string,
 *          controller: string,
 *          action: string,
 *      },
 *      context: array<string,string>,
 *      host: string,
 *      revision_sha: string,
 * }
 */
final class ErrorEvent
{
    /** @var ?Request */
    private $request;
    /** @var class-string */
    private $exceptionClass;
    /** @var non-empty-string */
    private $message;
    /** @var list<string> */
    private $formattedTrace;

    /**
     * @psalm-param class-string $exceptionClass
     * @psalm-param list<string> $formattedTrace
     */
    private function __construct(?Request $request, string $exceptionClass, string $message, array $formattedTrace)
    {
        if ($message === '') {
            $message = 'Oh dear - Scout could not find a message for this error or exception';
        }

        $this->request        = $request;
        $this->exceptionClass = $exceptionClass;
        $this->message        = $message;
        $this->formattedTrace = $formattedTrace;
    }

    public static function fromThrowable(?Request $request, Throwable $throwable): self
    {
        return new self(
            $request,
            get_class($throwable),
            $throwable->getMessage(),
            array_values(array_map(
                /** @psalm-param array{function: string, line: int, file: string, class?: string, type?: '->'|'::'} $trace */
                static function (array $trace): string {
                    return sprintf(
                        '%s:%d:in `%s`',
                        $trace['file'],
                        $trace['line'],
                        array_key_exists('class', $trace) && array_key_exists('type', $trace)
                            ? sprintf(
                                '%s%s%s',
                                $trace['class'],
                                str_replace($trace['type'], '::', '#'),
                                $trace['function']
                            )
                            : $trace['function']
                    );
                },
                $throwable->getTrace()
            ))
        );
    }

    /**
     * @param array<array-key, mixed> $session
     * @param array<array-key, mixed> $env
     *
     * @psalm-return ErrorEventJsonableArray
     */
    public function toJsonableArray(Config $config, array $session, array $env): array
    {
        $filteredParameters = Config\Helper\RequireValidFilteredUriParameters::fromConfig($config);

        return [
            'exception_class' => $this->exceptionClass,
            'message' => $this->message,
            'request_id' => $this->request ? $this->request->id()->toString() : null,
            'request_uri' => $this->request ? $this->request->requestPath() : 'Unable to detect URL, no request set',
            'request_params' => ['param1' => 'param2', 'param3' => ['a', 'b'], 'param4' => ['z1' => 'z2', 'z2' => 'z3']],
            'request_session' => FilterParameters::flattenedForUriReportingConfiguration($filteredParameters, $session),
            'environment' => FilterParameters::flattenedForUriReportingConfiguration($filteredParameters, $env),
            'trace' => $this->formattedTrace,
            'request_components' => [
                'module' => 'myModule', // @todo Seems ignored by Dashboard?
                'controller' => 'myController',
                'action' => 'myAction8',
            ],
            'context' => ['ctx1' => 'ctx2'], // @todo what is this?
            'host' => 'zabba1', // @todo populate from metadata
            'revision_sha' => 'abcabc', // @todo populate from metadata
        ];
    }
}
