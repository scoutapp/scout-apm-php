<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Helper\Superglobals;

use PHPUnit\Framework\TestCase;
use Scoutapm\Helper\Superglobals\SuperglobalsArrays;

/** @covers \Scoutapm\Helper\Superglobals\SuperglobalsArrays */
final class SuperglobalsArraysTest extends TestCase
{
    public function testFromGlobalState(): void
    {
        $oldServer  = $_SERVER;
        $oldEnv     = $_ENV;
        $oldSession = $_SESSION ?? [];
        $oldRequest = $_REQUEST;

        $_SERVER  = ['a' => 'a'];
        $_ENV     = ['b' => 'b'];
        $_SESSION = ['c' => 'c'];
        $_REQUEST = ['d' => 'd'];

        try {
            $superglobals = SuperglobalsArrays::fromGlobalState();

            self::assertEquals(['a' => 'a'], $superglobals->server());
            self::assertEquals(['b' => 'b'], $superglobals->env());
            self::assertEquals(['c' => 'c'], $superglobals->session());
            self::assertEquals(['d' => 'd'], $superglobals->request());
        } finally {
            $_ENV     = $oldEnv;
            $_SERVER  = $oldServer;
            $_SESSION = $oldSession;
            $_REQUEST = $oldRequest;
        }
    }
}
