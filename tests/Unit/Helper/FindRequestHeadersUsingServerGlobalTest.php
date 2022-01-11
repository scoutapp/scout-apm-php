<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Helper;

use PHPUnit\Framework\TestCase;
use Scoutapm\Helper\FindRequestHeaders\FindRequestHeadersUsingServerGlobal;
use Scoutapm\Helper\Superglobals\SuperglobalsArrays;

/** @covers \Scoutapm\Helper\FindRequestHeaders\FindRequestHeadersUsingServerGlobal */
final class FindRequestHeadersUsingServerGlobalTest extends TestCase
{
    public function testFromServerGlobal(): void
    {
        self::assertEquals(
            [
                'Document-Root' => '/path/to/public',
                'Remote-Addr' => '127.0.0.1',
                'Host' => 'scout-apm-test',
                'User-Agent' => 'Scout APM test',
                'Cookie' => 'cookie_a=null; cookie_b=null',
                'Accept' => '*/*',
                'X-Something-Custom' => 'Something custom',
            ],
            (new FindRequestHeadersUsingServerGlobal(new SuperglobalsArrays(
                [],
                [],
                [],
                [
                    'DOCUMENT_ROOT' => '/path/to/public',
                    'REMOTE_ADDR' => '127.0.0.1',
                    'HTTP_HOST' => 'scout-apm-test',
                    'HTTP_USER_AGENT' => 'Scout APM test',
                    'HTTP_COOKIE' => 'cookie_a=null; cookie_b=null',
                    'HTTP_ACCEPT' => '*/*',
                    'HTTP_X_SOMETHING_EMPTY' => '',
                    'HTTP_X_SOMETHING_CUSTOM' => 'Something custom',
                ]
            )))->__invoke()
        );
    }
}
