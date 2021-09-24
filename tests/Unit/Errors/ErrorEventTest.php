<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Errors;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Scoutapm\Config;
use Scoutapm\Errors\ErrorEvent;
use Scoutapm\Events\Request\Request;

use function array_key_exists;
use function get_class;
use function uniqid;

/** @covers \Scoutapm\Errors\ErrorEvent */
final class ErrorEventTest extends TestCase
{
    public function testToJsonableArrayWithMetadata(): void
    {
        $_SERVER['HTTPS']     = 'on';
        $_SERVER['HTTP_HOST'] = 'the-great-website';

        $config = Config::fromArray([
            Config\ConfigKey::HOSTNAME => 'zappa1',
            Config\ConfigKey::REVISION_SHA => 'abcabc',
        ]);

        $exceptionMessage = uniqid('the exception message', true);
        $exception        = new RuntimeException($exceptionMessage);
        $request          = Request::fromConfigAndOverrideTime($config);
        $request->overrideRequestUri('/path/to/thething');
        $jsonableArrayForEvent = ErrorEvent::fromThrowable($request, $exception)
            ->toJsonableArray(
                $config,
                ['sessionKey' => 'sessionValue'],
                ['envKey' => 'envValue'],
                [
                    'paramKey' => 'paramValue',
                    'paramList' => ['paramListItem1', 'paramListItem2'],
                    'paramDict' => ['paramDictKey' => 'paramDictValue'],
                ]
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
                'module' => 'myModule',
                'controller' => 'myController',
                'action' => 'myAction8',
            ],
            $jsonableArrayForEvent['request_components']
        );
        self::assertTrue(array_key_exists('context', $jsonableArrayForEvent));
        self::assertEquals(
            ['ctx1' => 'ctx2'],
            $jsonableArrayForEvent['context']
        );
        self::assertSame('zappa1', $jsonableArrayForEvent['host']);
        self::assertSame('abcabc', $jsonableArrayForEvent['revision_sha']);
    }
}
