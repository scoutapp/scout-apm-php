<?php

declare(strict_types=1);

namespace Scoutapm\CoreAgent;

use Psr\Log\LoggerInterface;
use Scoutapm\Config;
use Throwable;
use function exec;
use function file_get_contents;
use function hash;

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
        $this->coreAgentDir = $config->get('core_agent_dir') . '/' . $config->get('core_agent_full_name');

        $this->downloader = $downloader;
    }

    public function launch() : bool
    {
        if (! $this->config->get('core_agent_launch')) {
            $this->logger->debug("Not attempting to launch Core Agent due to 'core_agent_launch' setting.");

            return false;
        }

        if (! $this->verify()) {
            if (! $this->config->get('core_agent_download')) {
                $this->logger->debug(
                    "Not attempting to download Core Agent due to 'core_agent_download' setting."
                );

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
            // @todo ESCAPE THIS !!!
            $command = $this->agentBinary() . ' ' .
                $this->daemonizeFlag() . ' ' .
                $this->logLevel() . ' ' .
                $this->logFile() . ' ' .
                $this->configFile() . ' ' .
                $this->socketPath();
            $this->logger->debug('Core Agent: ' . $command);
            exec($command);

            return true;
        } catch (Throwable $e) {
            // TODO detect failure of launch properly
            // logger.error("Error running Core Agent: %r", e);
            return false;
        }
    }

    private function agentBinary() : string
    {
        // @todo should this be an exception...?
        if ($this->coreAgentBinPath === null) {
            return ' start';
        }

        return $this->coreAgentBinPath . ' start';
    }

    private function daemonizeFlag() : string
    {
        return '--daemonize true';
    }

    private function logLevel() : string
    {
        $log_level = $this->config->get('logLevel');
        if ($log_level !== null) {
            return '--log-level ' . $log_level;
        }

        return '';
    }

    /**
     * Core Agent log file. Does not affect any logging in the PHP side of the agent. Useful only for debugging purposes.
     */
    private function logFile() : string
    {
        $log_file = $this->config->get('logFile');
        if ($log_file !== null) {
            return '--log-file ' . $log_file;
        }

        return '';
    }

    /**
     * Allow a config file to be passed (this is distinct from the php configuration, this is only used for core-agent
     * specific configs, mostly for debugging, or other niche cases)
     */
    private function configFile() : string
    {
        $config = $this->config->get('configFile');
        if ($config !== null) {
            return '--config-file ' . $config;
        }

        return '';
    }

    private function socketPath() : string
    {
        return '--socket ' . $this->config->get('socket_path');
    }
}
