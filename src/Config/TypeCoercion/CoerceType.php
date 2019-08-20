<?php

declare(strict_types=1);

namespace Scoutapm\Config\TypeCoercion;

/** @internal */
interface CoerceType
{
    /**
     * @param mixed $input
     *
     * @return mixed
     */
    public function coerce($input);
}
