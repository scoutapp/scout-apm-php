<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Laravel\View\Engine;

use Illuminate\Contracts\View\Engine;
use Illuminate\View\Compilers\CompilerInterface;

class EngineImplementationWithGetCompilerMethod implements Engine
{
    /** @inheritDoc */
    public function get($path, array $data = [])
    {
        return '';
    }

    public function getCompiler(): CompilerInterface
    {
        return new class implements CompilerInterface {
            /** @inheritDoc */
            public function getCompiledPath($path): string
            {
                return '';
            }

            /** @inheritDoc */
            public function isExpired($path): bool
            {
                return true;
            }

            /** @inheritDoc */
            public function compile($path): void
            {
            }
        };
    }

    public function forgetCompiledOrNotExpired(): void
    {
    }
}
