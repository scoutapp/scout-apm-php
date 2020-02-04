<?php

declare(strict_types=1);

namespace Scoutapm\CoreAgent;

use Psr\Log\LoggerInterface;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;
use function hash_equals;
use function hash_file;
use function sprintf;

/** @internal */
final class AutomaticDownloadAndLaunchManager implements Manager
{
    /** @var Config */
    private $config;
    /** @var LoggerInterface */
    private $logger;
    /** @var string */
    private $coreAgentDir;
    /** @var Downloader */
    private $downloader;
    /** @var Launcher */
    private $launcher;

    public function __construct(Config $config, LoggerInterface $logger, Downloader $downloader, Launcher $launcher)
    {
        $this->config       = $config;
        $this->logger       = $logger;
        $this->coreAgentDir = $config->get(ConfigKey::CORE_AGENT_DIRECTORY) . '/' . $config->get(ConfigKey::CORE_AGENT_FULL_NAME);

        $this->downloader = $downloader;
        $this->launcher   = $launcher;
    }

    public function launch() : bool
    {
        if (! $this->config->get(ConfigKey::CORE_AGENT_LAUNCH_ENABLED)) {
            $this->logger->debug(sprintf(
                "Not attempting to launch Core Agent due to '%s' setting.",
                ConfigKey::CORE_AGENT_LAUNCH_ENABLED
            ));

            return false;
        }

        $coreAgentBinPath = $this->verify();
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

        $coreAgentBinPath = $this->verify();
        if ($coreAgentBinPath === null) {
            $this->logger->debug(
                'Failed to verify Core Agent. Not launching Core Agent.'
            );

            return false;
        }

        return $this->launcher->launch($coreAgentBinPath);
    }

    private function verify() : ?string
    {
        // Check for a well formed manifest
        $manifest = new Manifest($this->coreAgentDir . '/manifest.json', $this->logger);
        if (! $manifest->isValid()) {
            $this->logger->debug('Core Agent verification failed: Manifest is not valid.');

            return null;
        }

        // Check that the hash matches
        $binPath = $this->coreAgentDir . '/' . $manifest->binaryName();
        if (hash_equals($manifest->hashOfBinary(), hash_file('sha256', $binPath))) {
            return $binPath;
        }

        $this->logger->debug('Core Agent verification failed: SHA mismatch.');

        return null;
    }
}
