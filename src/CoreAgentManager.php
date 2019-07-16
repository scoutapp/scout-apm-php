<?php

namespace Scoutapm;

use PharData;

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
            $this->agent->getConfig()->get("core_agent_full_name"),
            $agent
        );
    }

    /**
     * @return void
     */
    public function launch()
    {
        if (! $this->agent->getConfig()->get("core_agent_launch")) {
            $this->agent->getLogger()->debug(
                "Not attempting to launch Core Agent due to 'core_agent_launch' setting."
            );
            return false;
        }

        if (! $this->verify()) {
            if (! $this->agent->getConfig()->get("core_agent_download")) {
                $this->agent->getLogger()->debug(
                    "Not attempting to download Core Agent due to 'core_agent_download' setting."
                );
                return false;
            }

            $this->download();
        }


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

    public function verify()
    {
        // Check for a well formed manifest
        $manifest = new CoreAgentManifest($this->coreAgentDir . "/manifest.json", $this->agent);
        if (! $manifest->isValid()) {
            $this->agent->getLogger()->debug("Core Agent verification failed: CoreAgentManifest is not valid.");
            $this->core_agent_bin_path = null;
            $this->core_agent_bin_version = null;
            return false;
        }

        // Check that the hash matches
        $binPath = $this->coreAgentDir . "/" . $manifest->binName;
        if (hash("sha256", file_get_contents($binPath)) == $manifest->sha256) {
            $this->core_agent_bin_path = $binPath;
            $this->core_agent_bin_version = $manifest->binVersion;
            return true;
        } else {
            $this->agent->getLogger()->debug("Core Agent verification failed: SHA mismatch.");
            $this->core_agent_bin_path = null;
            $this->core_agent_bin_version = null;
            return false;
        }
    }

    /**
     *
     *
     * @return void
     */
    public function run()
    {
        $this->agent->getLogger()->debug("Core Agent Launch in Progress");
        try {
            $command = $this->agent_binary() . " " .
                $this->daemonize_flag() . " " .
                $this->log_level() . " " .
                $this->log_file() . " " .
                $this->config_file() . " " .
                $this->socket_path();
            $this->agent->getLogger()->debug("Core Agent: ".$command);
            exec($command);
            return true;
        } catch (Exception $e) {
            // TODO detect failure of launch properly
            // logger.error("Error running Core Agent: %r", e);
            return false;
        }
    }

    public function agent_binary()
    {
        return $this->core_agent_bin_path . " start";
    }

    public function daemonize_flag()
    {
        return "--daemonize true";
    }

    public function log_level()
    {
        $log_level = $this->agent->getConfig()->get('log_level');
        if (!is_null($log_level)) {
            return "--log-level " . $log_level;
        } else {
            return "";
        }
    }

    // Core Agent log file. Does not affect any logging in the PHP side of the
    // agent. Useful only for debugging purposes.
    public function log_file()
    {
        $log_file = $this->agent->getConfig()->get('log_file');
        if (!is_null($log_file)) {
            return "--log-file " . $log_file;
        } else {
            return "";
        }
    }

    // Allow a config file to be passed (this is distinct from the
    // php configuration, this is only used for core-agent specific
    // configs, mostly for debugging, or other niche cases)
    public function config_file()
    {
        $config = $this->agent->getConfig()->get('config_file');
        if (!is_null($config)) {
            return "--config-file " . $config;
        } else {
            return "";
        }
    }

    public function socket_path()
    {
        return "--socket " . $this->agent->getConfig()->get('socket_path');
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
     * @param $coreAgentDir
     * @param $coreAgentFullName
     * @param $agent
     */
    public function __construct($coreAgentDir, $coreAgentFullName, $agent)
    {
        $this->coreAgentDir = $coreAgentDir;
        $this->coreAgentFullName = $coreAgentFullName;
        $this->agent = $agent;
        $this->stale_download_secs = 120;

        $this->package_location = $coreAgentDir . "/". $coreAgentFullName . ".tgz";
        $this->download_lock_path = $coreAgentDir . "/download.lock";
        $this->download_lock_fd = null;
    }

    public function download()
    {
        $this->create_core_agent_dir();
        $this->obtain_download_lock();

        if ($this->download_lock_fd != null) {
            try {
                $this->download_package();
                $this->untar();
            } catch (Exception $e) {
                $this->agent->getLogger()->error("Exception raised while downloading Core Agent: ". $e);
            } finally {
                $this->release_download_lock();
            }
        }
        return null;
    }

    public function create_core_agent_dir()
    {
        try {
            $permissions = 0777; // TODO: AgentContext.instance.config.core_agent_permissions()
            $recursive = true;
            $destination = $this->coreAgentDir;

            if (!is_dir($destination)) {
                mkdir($destination, $permissions, $recursive);
            }
        } catch (Exception $e) {
            $this->agent->getLogger()->error("Failed to create directory: " . $destination);
        }
    }

    public function obtain_download_lock()
    {
        $this->clean_stale_download_lock();

        try {
            $this->download_lock_fd = fopen(
                $this->download_lock_path,
                "x+" // This is the same as O_RDWR | O_EXCL | O_CREAT
                // O_RDWR | O_CREAT | O_EXCL | O_NONBLOCK
            );
        } catch (Exception $e) {
            $this->agent->getLogger()->debug("Could not obtain download lock on ".$this->download_lock_path . ": ". $e);
            $this->download_lock_fd = null;
        }
    }

    public function clean_stale_download_lock()
    {
        try {
            $delta = time() - filectime($this->download_lock_path);
            if ($delta > $this->stale_download_secs) {
                $this->agent->getLogger()->debug("Clearing stale download lock file.");
                unlink($this->download_lock_path);
            }
        } catch (\Exception $e) {
            // Log this
        }
    }

    public function release_download_lock()
    {
        if ($this->download_lock_fd != null) {
            fclose($this->download_lock_fd);
            unlink($this->download_lock_path);
        }
    }

    public function download_package()
    {
        file_put_contents(
            $this->package_location,
            file_get_contents($this->full_url())
        );
    }

    public function untar()
    {
        $destination = $this->coreAgentDir;

        // Uncompress the .tgz
        $phar = new PharData($this->package_location);
        $phar->decompress();

        // Extract it to destination
        $tar_location = dirname($this->package_location) . "/" . basename($this->package_location, '.tgz') . '.tar';
        $phar = new PharData($tar_location);
        $phar->extractTo($destination);
    }

    // The URL to download the agent package from
    public function full_url()
    {
        $root_url = $this->agent->getConfig()->get("download_url");
        return $root_url . "/" . $this->coreAgentFullName . ".tgz";
    }
}

class CoreAgentManifest
{
    public function __construct($path, $agent)
    {
        $this->manifest_path = $path;
        $this->agent = $agent;

        try {
            $this->parse();
        } catch (\Exception $e) {
            $this->valid = false;
        }
    }

    public function parse()
    {
        $this->agent->getLogger()->info("Parsing Core Agent Manifest at ". $this->manifest_path);

        $raw = file_get_contents($this->manifest_path);
        $json = json_decode($raw, true); // decode the JSON into an associative array

        $this->version = $json["version"];
        $this->binVersion = $json["core_agent_version"];
        $this->binName = $json["core_agent_binary"];
        $this->sha256 = $json["core_agent_binary_sha256"];
        $this->valid = true;
    }

    public function isValid()
    {
        return $this->valid;
    }
}
