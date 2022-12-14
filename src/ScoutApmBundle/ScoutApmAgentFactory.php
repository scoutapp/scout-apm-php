<?php

declare(strict_types=1);

namespace Scoutapm\ScoutApmBundle;

use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Scoutapm\Agent;
use Scoutapm\Config;
use Scoutapm\Connector\Connector;
use Scoutapm\Extension\ExtensionCapabilities;
use Scoutapm\ScoutApmAgent;
use Symfony\Component\HttpKernel\Kernel;

use function array_filter;
use function array_merge;

final class ScoutApmAgentFactory
{
    /** @param array<string, mixed> $agentConfiguration */
    public static function createAgent(
        LoggerInterface $logger,
        ?CacheInterface $cache,
        ?Connector $connector,
        ?ExtensionCapabilities $extensionCapabilities,
        array $agentConfiguration
    ): ScoutApmAgent {
        return Agent::fromConfig(
            Config::fromArray(array_merge(
                [
                    Config\ConfigKey::FRAMEWORK => 'Symfony',
                    Config\ConfigKey::FRAMEWORK_VERSION => Kernel::VERSION,
                ],
                array_filter($agentConfiguration)
            )),
            $logger,
            $cache,
            $connector,
            $extensionCapabilities
        );
    }
}
