<?php

declare(strict_types=1);

namespace Scoutapm\CoreAgent;

use Psr\Log\LoggerInterface;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;

use function sprintf;

/** @internal */
final class AutomaticDownloadAndLaunchManager implements Manager
{
    /** @var Config */
    private $config;
    /** @var LoggerInterface */
    private $logger;
    /** @var Downloader */
    private $downloader;
    /** @var Launcher */
    private $launcher;
    /** @var Verifier */
    private $verifier;

    public function __construct(
        Config $config,
        LoggerInterface $logger,
        Downloader $downloader,
        Launcher $launcher,
        Verifier $verifier
    ) {
        $this->config = $config;
        $this->logger = $logger;

        $this->downloader = $downloader;
        $this->launcher   = $launcher;
        $this->verifier   = $verifier;
    }

    public function launch(): bool
    {
        if (! $this->config->get(ConfigKey::CORE_AGENT_LAUNCH_ENABLED)) {
            $this->logger->debug(sprintf(
                "Not attempting to launch Core Agent due to '%s' setting.",
                ConfigKey::CORE_AGENT_LAUNCH_ENABLED
            ));

            return false;
        }

        $coreAgentBinPath = $this->verifier->verify();
        if ($coreAgentBinPath === null) {
            if (! $this->config->get(ConfigKey::CORE_AGENT_DOWNLOAD_ENABLED)) {
                $this->logger->debug(sprintf(
                    "Not attempting to download Core Agent due to '%s' setting.",
                    ConfigKey::CORE_AGENT_DOWNLOAD_ENABLED
                ));

                return false;
            }

            $this->downloader->download();
        }

        $coreAgentBinPath = $this->verifier->verify();
        if ($coreAgentBinPath === null) {
            $this->logger->debug(
                'Failed to verify Core Agent. Not launching Core Agent.'
            );

            return false;
        }

        return $this->launcher->launch($coreAgentBinPath);
    }
}
