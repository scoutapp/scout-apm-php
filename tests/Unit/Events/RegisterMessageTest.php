<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events;

use PHPUnit\Framework\TestCase;
use Scoutapm\Events\RegisterMessage;

/** @covers \Scoutapm\Events\RegisterMessage */
final class RegisterMessageTest extends TestCase
{
    public function testRegisterMessageSerializes(): void
    {
        self::assertEquals(
            [
                'Register' => [
                    'app' => 'app name',
                    'key' => 'app key',
                    'language' => 'php',
                    'api_version' => 'api version',
                ],
            ],
            (new RegisterMessage('app name', 'app key', 'api version'))->jsonSerialize()
        );
    }
}
