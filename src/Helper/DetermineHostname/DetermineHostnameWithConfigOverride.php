<?php

declare(strict_types=1);

namespace Scoutapm\Helper\DetermineHostname;

use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;

use function gethostname;

/** @internal */
final class DetermineHostnameWithConfigOverride implements DetermineHostname
{
    /** @var Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function __invoke(): string
    {
        return (string) ($this->config->get(ConfigKey::HOSTNAME) ?? gethostname());
    }
}
