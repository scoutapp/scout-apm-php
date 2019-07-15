<?php

namespace Scoutapm\Config;

class BoolCoercion
{
    public function coerce($value) : bool
    {
        if (is_null($value)) {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ["yes", "t", "true", "1"]);
        }
        
        return false;
    }
}
