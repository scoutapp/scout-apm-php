<?php

declare(strict_types=1);

namespace Scoutapm\Events\Span;

use Exception;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class SpanId
{
    /** @var UuidInterface */
    private $spanId;

    private function __construct(UuidInterface $spanId)
    {
        $this->spanId = $spanId;
    }

    /** @throws Exception */
    public static function new(): self
    {
        return new self(Uuid::uuid4());
    }

    public function toString(): string
    {
        return $this->spanId->toString();
    }
}
