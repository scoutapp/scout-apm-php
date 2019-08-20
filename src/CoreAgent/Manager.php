<?php

declare(strict_types=1);

namespace Scoutapm\CoreAgent;

use Scoutapm\Agent;
use Throwable;
use function exec;
use function file_get_contents;
use function hash;

/** @internal */
class Manager
{
    /**
     * A reference to the agent, from which we can obtain the logger and config.
     *
     * @var Agent
     */
    private $agent;

    /** @var string */
    private $coreAgentDir;

    /** @var Downloader */
    private $downloader;

    /** @var string|null */
    private $coreAgentBinPath;

    // phpcs:disable SlevomatCodingStandard.Classes.UnusedPrivateElements.WriteOnlyProperty

    /** @var string|null */
    private $coreAgentBinVersion;

    // phpcs:enable

    public function __construct(Agent $agent)
    {
        $this->agent        = $agent;
        $this->coreAgentDir = $agent->getConfig()->get('core_agent_dir') . '/' . $agent->getConfig()->get('core_agent_full_name');

        $this->downloader = new Downloader(
            $this->coreAgentDir,
            $this->agent->getConfig()->get('core_agent_full_name'),
            $agent
        );
    }

    public function launch() : bool
    {
        if (! $this->agent->getConfig()->get('core_agent_launch')) {
            $this->agent->getLogger()->debug(
                "Not attempting to launch Core Agent due to 'core_agent_launch' setting."
            );

            return false;
        }

        if (! $this->verify()) {
            if (! $this->agent->getConfig()->get('core_agent_download')) {
                $this->agent->getLogger()->debug(
                    "Not attempting to download Core Agent due to 'core_agent_download' setting."
                );

                return false;
            }

            $this->download();
        }

        if (! $this->verify()) {
            $this->agent->getLogger()->debug(
                'Failed to verify Core Agent. Not launching Core Agent.'
            );

            return false;
        }

        return $this->run();
    }

    /**
     * Initiate download of the agent
     */
    public function download() : void
    {
        $this->downloader->download();
    }

    public function verify() : bool
    {
        // Check for a well formed manifest
        $manifest = new Manifest($this->coreAgentDir . '/manifest.json', $this->agent);
        if (! $manifest->isValid()) {
            $this->agent->getLogger()->debug('Core Agent verification failed: Manifest is not valid.');
            $this->coreAgentBinPath    = null;
            $this->coreAgentBinVersion = null;

            return false;
        }

        // Check that the hash matches
        $binPath = $this->coreAgentDir . '/' . $manifest->binName;
        if (hash('sha256', file_get_contents($binPath)) === $manifest->sha256) {
            $this->coreAgentBinPath    = $binPath;
            $this->coreAgentBinVersion = $manifest->binVersion;

            return true;
        }

        $this->agent->getLogger()->debug('Core Agent verification failed: SHA mismatch.');
        $this->coreAgentBinPath    = null;
        $this->coreAgentBinVersion = null;

        return false;
    }

    public function run() : bool
    {
        $this->agent->getLogger()->debug('Core Agent Launch in Progress');
        try {
            $command = $this->agentBinary() . ' ' .
                $this->daemonizeFlag() . ' ' .
                $this->logLevel() . ' ' .
                $this->logFile() . ' ' .
                $this->configFile() . ' ' .
                $this->socketPath();
            $this->agent->getLogger()->debug('Core Agent: ' . $command);
            exec($command);

            return true;
        } catch (Throwable $e) {
            // TODO detect failure of launch properly
            // logger.error("Error running Core Agent: %r", e);
            return false;
        }
    }

    public function agentBinary() : string
    {
        // @todo should this be an exception...?
        if ($this->coreAgentBinPath === null) {
            return ' start';
        }

        return $this->coreAgentBinPath . ' start';
    }

    public function daemonizeFlag() : string
    {
        return '--daemonize true';
    }

    public function logLevel() : string
    {
        $log_level = $this->agent->getConfig()->get('logLevel');
        if ($log_level !== null) {
            return '--log-level ' . $log_level;
        }

        return '';
    }

    /**
     * Core Agent log file. Does not affect any logging in the PHP side of the agent. Useful only for debugging purposes.
     */
    public function logFile() : string
    {
        $log_file = $this->agent->getConfig()->get('logFile');
        if ($log_file !== null) {
            return '--log-file ' . $log_file;
        }

        return '';
    }

    /**
     * Allow a config file to be passed (this is distinct from the php configuration, this is only used for core-agent
     * specific configs, mostly for debugging, or other niche cases)
     */
    public function configFile() : string
    {
        $config = $this->agent->getConfig()->get('configFile');
        if ($config !== null) {
            return '--config-file ' . $config;
        }

        return '';
    }

    public function socketPath() : string
    {
        return '--socket ' . $this->agent->getConfig()->get('socketPath');
    }
}
