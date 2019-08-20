<?php

declare(strict_types=1);

namespace Scoutapm\Config;

use function is_string;
use function json_decode;

class JSONCoercion
{
    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public function coerce($value)
    {
        if (is_string($value)) {
            // assoc=true to create an array instead of an object
            return json_decode($value, true);
        }

        return $value;
    }
}
