<?php

declare(strict_types=1);

namespace Scoutapm\Helper;

use function file_exists;

/** @internal */
class LibcDetection
{
    private const ETC_ALPINE_RELEASE_PATH = '/etc/alpine-release';

    /** @var string */
    private $etcAlpineReleasePath;

    /** @internal */
    public function __construct(string $alpinePath = self::ETC_ALPINE_RELEASE_PATH)
    {
        $this->etcAlpineReleasePath = $alpinePath;
    }

    /** @internal */
    public function detect(): string
    {
        if (file_exists($this->etcAlpineReleasePath)) {
            return 'musl';
        }

        return 'gnu';
    }
}
