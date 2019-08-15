<?php

namespace Scoutapm\Config;

class JSONCoercion
{
    public function coerce($value)
    {
        if (is_string($value)) {
            // assoc=true to create an array instead of an object
            return json_decode($value, true);
        }
        
        return $value;
    }
}
