<?php

declare(strict_types=1);

namespace Scoutapm\Errors;

use Scoutapm\Events\Request\RequestId;
use Throwable;

use function get_class;

/**
 * @internal This is not covered by BC promise
 *
 * @psalm-type ErrorEventJsonableArray = array{
 *      exception_class: class-string,
 *      message: non-empty-string,
 *      request_id: string,
 *      request_uri: non-empty-string,
 *      request_params?: array<string,string>,
 *      request_session?: array<string,string>,
 *      environment?: array<string,string>,
 *      trace: array,
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
    /** @var RequestId */
    private $requestId;
    /** @var class-string */
    private $exceptionClass;
    /** @var non-empty-string */
    private $message;

    /**
     * @psalm-param class-string $exceptionClass
     */
    private function __construct(RequestId $requestId, string $exceptionClass, string $message)
    {
        if ($message === '') {
            $message = 'Oh dear - Scout could not find a message for this error or exception';
        }

        $this->requestId      = $requestId;
        $this->exceptionClass = $exceptionClass;
        $this->message        = $message;
    }

    public static function fromThrowable(RequestId $requestId, Throwable $throwable): self
    {
        return new self(
            $requestId,
            get_class($throwable),
            $throwable->getMessage()
        );
    }

    /**
     * @psalm-return ErrorEventJsonableArray
     */
    public function toJsonableArrayWithMetadata(/** @todo pass metadata source */): array
    {
        return [
            'exception_class' => $this->exceptionClass,
            'message' => $this->message,
            'request_id' => $this->requestId->toString(),
            'request_uri' => 'https://mysite.com/path/to/thething',
            'request_params' => ['param1' => 'param2'],
            'request_session' => ['sess1' => 'sess2'],
            'environment' => ['env1' => 'env2'],
            'trace' => [ // @todo find out trace format
                'test1.php:123:in myFunc()',
                'test2.php:234:in MyNs\\MyClass::myFunc()',
                'test3.php:345:in MyClass_Foo::myFunc()',
                'test4.php:456:in Woo::myFunc',
                'test5.php:567:in myFuncB',
            ],
            'request_components' => [
                'module' => 'myModule', // @todo Seems ignored by Dashboard?
                'controller' => 'myController',
                'action' => 'myAction3',
            ],
            'context' => ['ctx1' => 'ctx2'], // @todo what is this?
            'host' => 'zabba1', // @todo populate from metadata
            'revision_sha' => 'abcabc', // @todo populate from metadata
        ];
    }
}
