<?php

declare(strict_types=1);

namespace Scoutapm\Extension;

use RuntimeException;
use Webmozart\Assert\Assert;

use function preg_match;
use function sprintf;

final class Version
{
    /**
     * This regex is originally extracted from https://github.com/nikolaposa/version
     *
     * Copyright (c) Nikola Posa
     *
     * @link https://github.com/nikolaposa/version/blob/master/LICENSE
     * @link https://github.com/nikolaposa/version/blob/78e9d4ccf17004510aa3364fa0866a16f963a9fb/src/Version.php#L18
     *
     * Note: we explicitly don't parse pre-releases/build numbers, only major/minor/patch type versions.
     */
    private const REGEX = '#^(v|release\-)?(?P<major>0|[1-9]\d*)\.(?P<minor>0|[1-9]\d*)\.(?P<patch>0|[1-9]\d*)$#';

    /** @var int */
    private $major;
    /** @var int */
    private $minor;
    /** @var int */
    private $patch;

    private function __construct(int $major, int $minor, int $patch)
    {
        Assert::greaterThanEq($major, 0);
        Assert::greaterThanEq($minor, 0);
        Assert::greaterThanEq($patch, 0);

        $this->major = $major;
        $this->minor = $minor;
        $this->patch = $patch;
    }

    public static function fromString(string $versionString): self
    {
        if (! preg_match(self::REGEX, $versionString, $parts)) {
            throw new RuntimeException(sprintf('Unable to parse version %s', $versionString));
        }

        return new self((int) $parts['major'], (int) $parts['minor'], (int) $parts['patch']);
    }

    public function isOlderThan(self $otherVersion): bool
    {
        return (($this->major * 1000000) + ($this->minor * 1000) + $this->patch)
            < (($otherVersion->major * 1000000) + ($otherVersion->minor * 1000) + $otherVersion->patch);
    }

    public function toString(): string
    {
        return sprintf('%d.%d.%d', $this->major, $this->minor, $this->patch);
    }
}
