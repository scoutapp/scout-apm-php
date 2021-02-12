<?php

declare(strict_types=1);

namespace Scoutapm\Events\Span;

/**
 * Public API wrapper for a Span, since a Span is an internal API for this library. This class can be considered a
 * promise for BC between major versions of the API.
 */
final class SpanReference
{
    public const INSTRUMENT_CONTROLLER = 'Controller';
    public const INSTRUMENT_JOB        = 'Job';
    public const INSTRUMENT_MIDDLEWARE = 'Middleware';

    /** @var Span */
    private $realSpan;

    private function __construct(Span $realSpan)
    {
        $this->realSpan = $realSpan;
    }

    public static function fromSpan(Span $realSpan): self
    {
        return new self($realSpan);
    }

    public function updateName(string $newName): void
    {
        $this->realSpan->updateName($newName);
    }

    /** @param mixed $value */
    public function tag(string $tag, $value): void
    {
        $this->realSpan->tag($tag, $value);
    }

    public function getName(): string
    {
        return $this->realSpan->getName();
    }

    public function getStartTime(): ?string
    {
        return $this->realSpan->getStartTime();
    }

    public function getStopTime(): ?string
    {
        return $this->realSpan->getStopTime();
    }

    public function duration(): ?float
    {
        return $this->realSpan->duration();
    }
}
