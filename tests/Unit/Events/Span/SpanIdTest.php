<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events\Span;

use Exception;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Scoutapm\Events\Span\SpanId;

/** @covers \Scoutapm\Events\Span\SpanId */
final class SpanIdTest extends TestCase
{
    /** @throws Exception */
    public function testSpanIdCanBeGeneratedAndConvertedToString(): void
    {
        self::assertTrue(Uuid::isValid(SpanId::new()->toString()));
    }
}
