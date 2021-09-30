<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Helper;

use PHPUnit\Framework\TestCase;
use Scoutapm\Helper\FetchRequestHeaders;

/** @covers \Scoutapm\Helper\FetchRequestHeaders */
final class FetchRequestHeadersTest extends TestCase
{
    public function testFromServerGlobal(): void
    {
        $oldServer = $_SERVER;
        $_SERVER   = [
            'SCRIPT_NAME' => $oldServer['SCRIPT_NAME'], // Needed for PHPUnit
            'REQUEST_TIME' => $oldServer['REQUEST_TIME'], // Needed for PHPUnit
            'DOCUMENT_ROOT' => '/path/to/public',
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => 'scout-apm-test',
            'HTTP_USER_AGENT' => 'Scout APM test',
            'HTTP_COOKIE' => 'cookie_a=null; cookie_b=null',
            'HTTP_ACCEPT' => '*/*',
            'HTTP_X_SOMETHING_EMPTY' => '',
            'HTTP_X_SOMETHING_CUSTOM' => 'Something custom',
            0 => 'should be dismissed',
            1 => '',
        ];

        self::assertEquals(
            [
                'Script-Name' => $oldServer['SCRIPT_NAME'],
                'Request-Time' => $oldServer['REQUEST_TIME'],
                'Document-Root' => '/path/to/public',
                'Remote-Addr' => '127.0.0.1',
                'Host' => 'scout-apm-test',
                'User-Agent' => 'Scout APM test',
                'Cookie' => 'cookie_a=null; cookie_b=null',
                'Accept' => '*/*',
                'X-Something-Custom' => 'Something custom',
            ],
            FetchRequestHeaders::fromServerGlobal()
        );
        $_SERVER = $oldServer;
    }
}
