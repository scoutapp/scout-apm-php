<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Errors;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Scoutapm\Errors\ErrorEvent;
use Scoutapm\Events\Request\RequestId;

use function get_class;
use function uniqid;

/** @covers \Scoutapm\Errors\ErrorEvent */
final class ErrorEventTest extends TestCase
{
    public function testToJsonableArrayWithMetadata(): void
    {
        $exceptionMessage = uniqid('the exception message', true);
        $exception        = new RuntimeException($exceptionMessage);
        $requestId        = RequestId::new();
        self::assertEquals(
            [
                'exception_class' => get_class($exception),
                'message' => $exceptionMessage,
                'request_id' => $requestId->toString(),
                'request_uri' => 'https://mysite.com/path/to/thething',
                'request_params' => [],
                'request_session' => [],
                'environment' => [],
                'trace' => [],
                'request_components' => [],
                'context' => [],
                'host' => 'zabba1',
                'revision_sha' => 'abcabc',
            ],
            ErrorEvent::fromThrowable($requestId, $exception)
                ->toJsonableArrayWithMetadata()
        );
    }
}
