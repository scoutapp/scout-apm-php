<?php

declare(strict_types=1);

namespace Scoutapm\Config\TypeCoercion;

use function is_string;
use function json_decode;

/** @internal */
final class CoerceJson implements CoerceType
{
    /** {@inheritDoc} */
    public function coerce($value)
    {
        if (is_string($value)) {
            // assoc=true to create an array instead of an object
            return json_decode($value, true);
        }

        return $value;
    }
}
