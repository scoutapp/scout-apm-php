<?php

declare(strict_types=1);

namespace Scoutapm\Config;

use Scoutapm\Config;
use function strlen;
use function substr;

/** @internal */
final class IgnoredEndpoints
{
    /** @var Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function ignored(string $url) : bool
    {
        $ignored = $this->config->get('ignore');
        if ($ignored === null) {
            return false;
        }

        foreach ($ignored as $ignore) {
            if (substr($url, 0, strlen($ignore)) === $ignore) {
                return true;
            }
        }

        // None Matched
        return false;
    }
}
