<?php

namespace Scoutapm\Coreagent;

use Exception;

class Manager
{

    private $coreAgentBinPath = null;
    private $coreAgentBinVersion = null;
    private $coreAgentDir;
    private $downloader;

    public function __construct()
    {
        $this->coreAgentDir = config('scoutapm.coreAgentDir');
        $this->downloader = new Downloader($this->coreAgentDir, config('scoutapm.coreAgentFullName'));
    }

    public function launch() : bool
    {
        if (! config('scoutapm.active')) {
            // Don't launch, due to config
            return false;
        }

        if (! $this->verify()) {
            $this->downloader->download();

            if (! $this->verify()) {
                // Don't launch, failed to verify
                return false;
            }
        }

        return $this->run();
    }

    public function run() : bool
    {
        try {
            $commands = [
                $this->getAgentBinary(),
                $this->getDaemonizeFlag(),
                $this->getLogLevel(),
                $this->getConfigFile(),
                $this->getSocketPath(),
            ];

            foreach ($commands as $key => $command) {
                if ($command === null) {
                    unset($commands[$key]);
                }
            }

            exec(implode(' ', $commands));
        }
        catch (Exception $exception) {
            return false;
        }

        return true;
    }

    public function getAgentBinary()
    {
        return config('core_agent_bin_path', 'start');
    }

    public function getDaemonizeFlag()
    {
        return '--daemonize true';
    }

    public function getSocketPath()
    {
        $socketPath = config('socket_path', '/tmp/core-agent.sock');
        return "--socket $socketPath";
    }

    public function getLogLevel()
    {
        $level = config('log_level');
        return "--log-level $level";
    }

    public function getConfigFile()
    {
        $path = config('config_file');

        if ($path === null) {
            return null;
        }

        return "--config-file $path";
    }

    public function verify() : bool
    {
        $manifest = new Manifest($this->coreAgentDir . '/manifest.json');
        if (! $manifest->isValid()) {
            return false;
        }

        $path = $this->coreAgentDir . '/' . $manifest->getBinaryName();
        if ($manifest->getHash() === hash_file("sha256", $path)) {
            $this->coreAgentBinPath = $path;
            $this->coreAgentBinVersion = $manifest->getBinaryVersion();

            return true;
        }

        // Hash mismatch
        return false;
    }
}
