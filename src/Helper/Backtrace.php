<?php

namespace Scoutapm\Helper;

class BackTrace
{
    public static function capture()
    {
        $stack = debug_backtrace();
        
        $formatted_stack = [];
        foreach ($stack as $frame) {
            if (isset($frame["file"]) && isset($frame["line"]) && isset($frame["function"])) {
                array_push($formatted_stack, ["file" => $frame["file"], "line" => $frame["line"], "function" => $frame["function"]]);
            }
        }

        return $formatted_stack;
    }
}
