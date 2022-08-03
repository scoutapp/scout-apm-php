<?php

declare(strict_types=1);

namespace Scoutapm\Config\TypeCoercion;

/** @internal */
final class CoerceString implements CoerceType
{
    /** {@inheritDoc} */
    public function coerce($value): string
    {
        return (string) $value;
    }
}
