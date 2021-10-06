<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Errors;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Scoutapm\Config;
use Scoutapm\Errors\ErrorEvent;
use Scoutapm\Events\Request\Request;
use Scoutapm\Helper\DetermineHostname\DetermineHostname;
use Scoutapm\Helper\FindRequestHeaders\FindRequestHeaders;
use Scoutapm\Helper\RootPackageGitSha\FindRootPackageGitSha;
use Scoutapm\Helper\Superglobals\SuperglobalsArrays;

use function array_key_exists;
use function get_class;
use function uniqid;

/** @covers \Scoutapm\Errors\ErrorEvent */
final class ErrorEventTest extends TestCase
{
    public function testToJsonableArray(): void
    {
        $config = Config::fromArray([]);

        $exceptionMessage = uniqid('the exception message', true);
        $exception        = new RuntimeException($exceptionMessage);

        $request = Request::fromConfigAndOverrideTime($config, $this->createMock(FindRequestHeaders::class));
        $request->startSpan('Controller/MyGreatController');
        $request->stopSpan();
        $request->tag('ContextTag', 'ContextValue');
        $request->overrideRequestUri('/path/to/thething');

        $determineHostname = $this->createMock(DetermineHostname::class);
        $determineHostname->method('__invoke')->willReturn('zappa1');

        $findRootPackageGitSha = $this->createMock(FindRootPackageGitSha::class);
        $findRootPackageGitSha->method('__invoke')->willReturn('abcabc');

        $jsonableArrayForEvent = ErrorEvent::fromThrowable($request, $exception)
            ->toJsonableArray(
                $config,
                new SuperglobalsArrays(
                    ['sessionKey' => 'sessionValue'],
                    [
                        'paramKey' => 'paramValue',
                        'paramList' => ['paramListItem1', 'paramListItem2'],
                        'paramDict' => ['paramDictKey' => 'paramDictValue'],
                    ],
                    ['envKey' => 'envValue'],
                    [
                        'HTTPS' => 'on',
                        'HTTP_HOST' => 'the-great-website',
                    ]
                ),
                $determineHostname,
                $findRootPackageGitSha
            );

        self::assertSame(get_class($exception), $jsonableArrayForEvent['exception_class']);
        self::assertSame($exceptionMessage, $jsonableArrayForEvent['message']);
        self::assertSame($request->id()->toString(), $jsonableArrayForEvent['request_id']);
        self::assertSame('https://the-great-website/path/to/thething', $jsonableArrayForEvent['request_uri']);
        self::assertTrue(array_key_exists('request_params', $jsonableArrayForEvent));
        self::assertEquals(
            [
                'paramKey' => 'paramValue',
                'paramList' => ['paramListItem1', 'paramListItem2'],
                'paramDict' => ['paramDictKey' => 'paramDictValue'],
            ],
            $jsonableArrayForEvent['request_params']
        );
        self::assertTrue(array_key_exists('request_session', $jsonableArrayForEvent));
        self::assertEquals(
            ['sessionKey' => 'sessionValue'],
            $jsonableArrayForEvent['request_session']
        );
        self::assertTrue(array_key_exists('environment', $jsonableArrayForEvent));
        self::assertEquals(
            ['envKey' => 'envValue'],
            $jsonableArrayForEvent['environment']
        );
        self::assertTrue(array_key_exists('trace', $jsonableArrayForEvent));
        foreach ($jsonableArrayForEvent['trace'] as $value) {
            self::assertStringMatchesFormat('%s:%d:in `%s`', $value);
        }

        self::assertTrue(array_key_exists('request_components', $jsonableArrayForEvent));
        self::assertEquals(
            [
                'module' => 'UnknownModule',
                'controller' => 'Controller',
                'action' => 'MyGreatController',
            ],
            $jsonableArrayForEvent['request_components']
        );
        self::assertTrue(array_key_exists('context', $jsonableArrayForEvent));
        self::assertEquals(
            ['ContextTag' => 'ContextValue'],
            $jsonableArrayForEvent['context']
        );
        self::assertSame('zappa1', $jsonableArrayForEvent['host']);
        self::assertSame('abcabc', $jsonableArrayForEvent['revision_sha']);
    }
}
