<?php

declare(strict_types=1);

namespace Scoutapm\CoreAgent;

use Psr\Log\LoggerInterface;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;
use Throwable;
use function array_map;
use function exec;
use function file_get_contents;
use function hash;
use function implode;
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

    /** @var string|null */
    private $coreAgentBinPath;

    public function __construct(Config $config, LoggerInterface $logger, Downloader $downloader)
    {
        $this->config       = $config;
        $this->logger       = $logger;
        $this->coreAgentDir = $config->get(ConfigKey::CORE_AGENT_DIRECTORY) . '/' . $config->get(ConfigKey::CORE_AGENT_FULL_NAME);

        $this->downloader = $downloader;
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

        if (! $this->verify()) {
            if (! $this->config->get(ConfigKey::CORE_AGENT_DOWNLOAD_ENABLED)) {
                $this->logger->debug(sprintf(
                    "Not attempting to download Core Agent due to '%s' setting.",
                    ConfigKey::CORE_AGENT_DOWNLOAD_ENABLED
                ));

                return false;
            }

            $this->download();
        }

        if (! $this->verify()) {
            $this->logger->debug(
                'Failed to verify Core Agent. Not launching Core Agent.'
            );

            return false;
        }

        return $this->run();
    }

    /**
     * Initiate download of the agent
     */
    private function download() : void
    {
        $this->downloader->download();
    }

    private function verify() : bool
    {
        // Check for a well formed manifest
        $manifest = new Manifest($this->coreAgentDir . '/manifest.json', $this->logger);
        if (! $manifest->isValid()) {
            $this->logger->debug('Core Agent verification failed: Manifest is not valid.');
            $this->coreAgentBinPath = null;

            return false;
        }

        // Check that the hash matches
        $binPath = $this->coreAgentDir . '/' . $manifest->binaryName();
        if (hash('sha256', file_get_contents($binPath)) === $manifest->hashOfBinary()) {
            $this->coreAgentBinPath = $binPath;

            return true;
        }

        $this->logger->debug('Core Agent verification failed: SHA mismatch.');
        $this->coreAgentBinPath = null;

        return false;
    }

    private function run() : bool
    {
        $this->logger->debug('Core Agent Launch in Progress');
        try {
            $logLevel   = $this->config->get(ConfigKey::CORE_AGENT_LOG_LEVEL);
            $logFile    = $this->config->get(ConfigKey::CORE_AGENT_LOG_FILE);
            $configFile = $this->config->get(ConfigKey::CORE_AGENT_CONFIG_FILE);

            if ($logFile === null) {
                $logFile = '/dev/null';
            }

            $commandParts = [
                $this->coreAgentBinPath,
                'start',
                '--daemonize',
                'true',
                '--log-file',
                $logFile,
            ];

            if ($logLevel !== null) {
                $commandParts[] = '--log-level';
                $commandParts[] = $logLevel;
            }

            if ($configFile !== null) {
                $commandParts[] = '--config-file';
                $commandParts[] = $configFile;
            }

            $commandParts[] = '--socket';
            $commandParts[] = $this->config->get(ConfigKey::CORE_AGENT_SOCKET_PATH);

            $escapedCommand = implode(' ', array_map('escapeshellarg', $commandParts));

            $this->logger->debug('Core Agent: ' . $escapedCommand);
            exec($escapedCommand);

            return true;
        } catch (Throwable $e) {
            // TODO detect failure of launch properly
            // logger.error("Error running Core Agent: %r", e);
            return false;
        }
    }
}
