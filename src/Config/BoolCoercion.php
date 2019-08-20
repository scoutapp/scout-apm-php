<?php

declare(strict_types=1);

namespace Scoutapm\Config;

use function in_array;
use function is_bool;
use function is_string;
use function strtolower;

class BoolCoercion
{
    /** @param mixed $value */
    public function coerce($value) : bool
    {
        if ($value === null) {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['yes', 't', 'true', '1']);
        }

        return false;
    }
}
