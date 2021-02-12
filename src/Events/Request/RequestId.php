<?php

declare(strict_types=1);

namespace Scoutapm\Events\Request;

use Exception;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class RequestId
{
    /** @var UuidInterface */
    private $requestId;

    private function __construct(UuidInterface $requestId)
    {
        $this->requestId = $requestId;
    }

    /** @throws Exception */
    public static function new(): self
    {
        return new self(Uuid::uuid4());
    }

    public function toString(): string
    {
        return $this->requestId->toString();
    }
}
