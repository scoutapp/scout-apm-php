<?php

declare(strict_types=1);

namespace Scoutapm\Errors;

use Scoutapm\Config;
use Scoutapm\Events\Request\Request;
use Scoutapm\Helper\DetermineHostname\DetermineHostname;
use Scoutapm\Helper\FilterParameters;
use Scoutapm\Helper\RootPackageGitSha\FindRootPackageGitSha;
use Scoutapm\Helper\Superglobals\Superglobals;
use Throwable;

use function array_key_exists;
use function array_map;
use function array_values;
use function assert;
use function explode;
use function get_class;
use function sprintf;
use function str_replace;

/**
 * @internal This is not covered by BC promise
 *
 * @psalm-type ErrorEventJsonStructure = array{
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
     * @psalm-param class-string<Throwable> $exceptionClass
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
                /** @psalm-param array{function: string, line?: int, file?: string, class?: string, type?: '->'|'::'} $trace */
                static function (array $trace): string {
                    return sprintf(
                        '%s:%d:in `%s`',
                        array_key_exists('file', $trace) ? $trace['file'] : 'unknown file',
                        array_key_exists('line', $trace) ? $trace['line'] : 0,
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

    /** @return non-empty-string */
    private function buildRequestUri(Superglobals $superglobals): string
    {
        $server  = $superglobals->server();
        $isHttps = array_key_exists('HTTPS', $server) && $server['HTTPS'] === 'on';
        $port    = array_key_exists('SERVER_PORT', $server) ? (int) $server['SERVER_PORT'] : 0;

        // phpcs:disable SlevomatCodingStandard.PHP.UselessParentheses.UselessParentheses
        $builtUrl = sprintf(
            '%s://%s%s%s',
            $isHttps ? 'https' : 'http',
            array_key_exists('HTTP_HOST', $server)
                ? $server['HTTP_HOST']
                : (array_key_exists('SERVER_NAME', $server) ? $server['SERVER_NAME'] : ''),
            ($port === 0 || (! $isHttps && $port === 80) || ($isHttps && $port === 443)) ? '' : (':' . $port),
            $this->request ? $this->request->requestPath() : $server['REQUEST_URI']
        );
        assert($builtUrl !== '');
        // phpcs:enable

        return $builtUrl;
    }

    /** @psalm-return ErrorEventJsonStructure */
    public function toJsonableArray(
        Config $config,
        Superglobals $superglobals,
        DetermineHostname $determineHostname,
        FindRootPackageGitSha $findRootPackageGitSha
    ): array {
        $filteredParameters = Config\Helper\RequireValidFilteredParameters::fromConfigForErrors($config);

        $controllerName = explode(
            '/',
            $this->request
                ? ($this->request->controllerOrJobName() ?? 'UnknownController/UnknownAction')
                : 'UnknownController/UnknownAction',
            2
        );

        return [
            'exception_class' => $this->exceptionClass,
            'message' => $this->message,
            'request_id' => $this->request ? $this->request->id()->toString() : null,
            'request_uri' => $this->buildRequestUri($superglobals),
            'request_params' => FilterParameters::flattenedForUriReportingConfiguration($filteredParameters, $superglobals->request(), 4),
            'request_session' => FilterParameters::flattenedForUriReportingConfiguration($filteredParameters, $superglobals->session()),
            'environment' => FilterParameters::flattenedForUriReportingConfiguration($filteredParameters, $superglobals->env()),
            'trace' => $this->formattedTrace,
            'request_components' => [
                'module' => 'UnknownModule',
                'controller' => $controllerName[0],
                'action' => $controllerName[1],
            ],
            'context' => $this->request ? $this->request->tags() : [],
            'host' => $determineHostname(),
            'revision_sha' => $findRootPackageGitSha(),
        ];
    }
}
