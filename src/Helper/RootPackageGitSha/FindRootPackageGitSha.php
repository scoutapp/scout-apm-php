<?php

declare(strict_types=1);

namespace Scoutapm\Helper\RootPackageGitSha;

/** @internal This is not covered by BC promise */
interface FindRootPackageGitSha
{
    /** @internal This is not covered by BC promise */
    public function __invoke(): string;
}
