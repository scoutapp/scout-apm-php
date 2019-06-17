<?php

namespace Scoutapm;

/**
 * Class CoreAgentManager
 */
class CoreAgentManager
{
    // A reference to the agent, from which we can obtain the logger and config.
    private $agent;

    private $coreAgentBinPath;
    private $coreAgentBinVersion;
    private $coreAgentDir;
    private $downloader;

    public function __construct(\Scoutapm\Agent $agent)
    {
        $this->agent = $agent;
        $this->coreAgentDir =
            $agent->getConfig()->get("core_agent_dir") .
            "/" .
            $agent->getConfig()->get("core_agent_full_name");

        $this->downloader = new CoreAgentDownloader(
            $this->coreAgentDir,
            $this->agent->getConfig()->get("core_agent_full_name")
        );
    }

    /**
     * undocumented function
     *
     * @return void
     */
    public function launch()
    {
        if (! $this->agent->getConfig()->value("core_agent_launch")) {
            $this->agent->getLogger()->debug(
                "Not attempting to launch Core Agent " .
                "due to 'core_agent_launch' setting."
            );
            return false;
        }

        if (! $this->verify()) {
            if (! $this->agent->getConfig()->value("core_agent_download")) {
                $this->agent->getLogger()->debug(
                    "Not attempting to download Core Agent due ".
                    "to 'core_agent_download' setting."
                );
                return false;
            }
        }

        $this->download();

        if (! $this->verify()) {
            $this->agent->getLogger()->debug(
                "Failed to verify Core Agent. Not launching Core Agent."
            );
            return false;
        }

        return $this->run();
    }

    /**
     * Initiate download of the agent
     *
     * @return void
     */
    public function download()
    {
        $this->downloader->download();
    }

    /**
     *
     *
     * @return void
     */
    public function run()
    {
        try {
            subprocess.check_call(
            self.agent_binary()
            + self.daemonize_flag()
            + self.log_level()
            + self.log_file()
            + self.config_file()
            + self.socket_path()
        );
            return true;
        } catch (Exception $e) {
            // TODO detect failure of launch properly
            // logger.error("Error running Core Agent: %r", e);
            return false;
        }
    }
}

/**
 * Class CoreAgentDownloader
 *
 * A helper class for the CoreAgentManager that handles downloading, verifying,
 * and unpacking the CoreAgent.
 */
class CoreAgentDownloader
{
    /**
     * @param $core_agent_dir
     * @param $core_agent_full_name
     */
    public function __construct($core_agent_dir, $core_agent_full_name)
    {
        $this->core_agent_dir = $core_agent_dir;
        $this->core_agent_full_name = $core_agent_full_name;
    }

    public function download()
    {
        return null;
    }
}
