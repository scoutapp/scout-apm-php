<?php

declare(strict_types=1);

namespace Scoutapm\Config\TypeCoercion;

interface CoerceType
{
    /**
     * @param mixed $input
     *
     * @return mixed
     */
    public function coerce($input);
}
