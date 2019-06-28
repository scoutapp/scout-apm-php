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
            exec(
                $this->agent_binary() .
                $this->daemonize_flag() .
                $this->log_level() .
                $this->log_file() .
                $this->config_file() .
                $this->socket_path()
            );
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
        // TODO: Make this reflect actual log level?
        return "--log-level info";
    }
    public function log_file()
    {
        // TODO: Make this reflect chosen log file
        return "";
    }
    public function config_file()
    {
        // TODO: Allow a config file to be passed (this is distinct from the
        // php configuration, this is only used for core-agent specific
        // configs, mostly for debugging)
        return "";
    }
    public function socket_path()
    {
        return "--socket " . $this->agent->getConfig()->value('socket_path');
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
        $this->create_core_agent_dir();
        $this->obtain_download_lock();

        if ($this->download_lock_fd != null) {
            try {
                $this->download_package();
                $this->untar();
            } catch (Exception $e) {
                // logger.error("Exception raised while downloading Core Agent: %r", e)
            } finally {
                $this->release_download_lock();
            }
        }
        return null;
    }

    public function create_core_agent_dir()
    {
        try {
            os.makedirs(
                self.destination,
                AgentContext.instance.config.core_agent_permissions()
            );
        } catch (Exception $e) {
            // Log this
        }
    }

    public function obtain_download_lock()
    {
        $this->clean_stale_download_lock();

        try {
            $this->download_lock_fd = os.open(
                self.download_lock_path,
                os.O_RDWR | os.O_CREAT | os.O_EXCL | os.O_NONBLOCK,
            );
        } catch (Exception $e) {
            // logger.debug( "Could not obtain download lock on %s: %r", self.download_lock_path, e);
            $this->download_lock_fd = null;
        }
    }

    public function clean_stale_download_lock()
    {
        try {
            $delta = time.time() - os.stat(self.download_lock_path).st_ctime;
            if (delta > self.stale_download_secs) {
                // logger.debug("Clearing stale download lock file.");
                os.unlink(self.download_lock_path);
            }
        } catch (Exception $e) {
            // Log this
        }
    }

    public function release_download_lock()
    {
        if ($this->download_lock_fd != null) {
            os.unlink(self.download_lock_path);
            os.close(self.download_lock_fd);
        }
    }

    public function download_package()
    {
        // logger.debug("Downloading: %s to %s", self.full_url(), self.package_location)
        // req = requests.get(self.full_url(), stream=True)
        // with open(self.package_location, "wb") as f:
        //     for chunk in req.iter_content(1024 * 1000):
        //         f.write(chunk)
    }

    public function untar()
    {
        $t = tarfile.open(self.package_location, "r");
        $t.extractall(self.destination);
    }

    public function full_url()
    {
        // return "{root_url}/{core_agent_full_name}.tgz".format(
        //     root_url=self.root_url(), core_agent_full_name=self.core_agent_full_name
        // )
    }

    public function root_url()
    {
        return $this->agent->getConfig()->value("download_url");
    }
}


// class CoreAgentManifest(object):
//     def __init__(self, path):
//         self.manifest_path = path
//         self.bin_name = None
//         self.bin_version = None
//         self.sha256 = None
//         self.valid = False
//         try:
//             self.parse()
//         except (ValueError, TypeError, OSError, IOError) as e:
//             logger.debug("Error parsing Core Agent Manifest: %r", e)

//     def parse(self):
//         logger.debug("Parsing Core Agent manifest path: %s", self.manifest_path)
//         with open(self.manifest_path) as manifest_file:
//             self.raw = manifest_file.read()
//             self.json = json.loads(self.raw)
//             self.version = self.json["version"]
//             self.bin_version = self.json["core_agent_version"]
//             self.bin_name = self.json["core_agent_binary"]
//             self.sha256 = self.json["core_agent_binary_sha256"]
//             self.valid = True
//             logger.debug("Core Agent manifest json: %s", self.json)

//     def is_valid(self):
//         return self.valid


// def sha256_digest(filename, block_size=65536):
//     try:
//         sha256 = hashlib.sha256()
//         with open(filename, "rb") as f:
//             for block in iter(lambda: f.read(block_size), b""):
//                 sha256.update(block)
//         return sha256.hexdigest()
//     except OSError as e:
//         logger.debug("Error on digest: %r", e)
//         return None
// }
