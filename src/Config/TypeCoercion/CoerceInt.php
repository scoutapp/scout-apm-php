<?php

declare(strict_types=1);

namespace Scoutapm\Config\TypeCoercion;

/** @internal */
final class CoerceInt implements CoerceType
{
    /** {@inheritDoc} */
    public function coerce($value): int
    {
        return (int) $value;
    }
}
