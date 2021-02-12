<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events\Request;

use Exception;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Scoutapm\Events\Request\RequestId;

/** @covers \Scoutapm\Events\Request\RequestId */
final class RequestIdTest extends TestCase
{
    /** @throws Exception */
    public function testRequestIdCanBeGeneratedAndConvertedToString(): void
    {
        self::assertTrue(Uuid::isValid(RequestId::new()->toString()));
    }
}
