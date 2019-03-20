<?php

namespace Scoutapm\Coreagent;

use Exception;
use PharData;

class Downloader
{
    private $destination;
    private $coreAgentFullName;
    private $packageLocation;
    private $downloadLock_path;
    private $downloadLockFd = null;

    public function __construct($destination, $name)
    {
        $this->destination = $destination;
        $this->coreAgentFullName = $name;
        $this->packageLocation = $this->destination . "/$name.tgz";
        $this->downloadLock_path = $this->destination . "/scout-download.lock";
    }

    public function download()
    {
        $this->createCoreAgentDirectory();

        if (! $this->downloadLockFd) {
            return;
        }

        $this->downloadPackage();
        $this->untar();
    }

    public function createCoreAgentDirectory() : bool
    {
        return mkdir($this->destination);
    }

    public function downloadPackage() : bool
    {
        $byteCount = file_put_contents($this->packageLocation, fopen($this->getFullUrl(), 'r'), LOCK_EX);

        return $byteCount !== false;
    }

    public function untar()
    {
        try {
            $tar = new PharData($this->packageLocation);
            $tar->extractTo($this->destination);
        }
        catch (Exception $exception) {
            return false;
        }

        return true;
    }

    public function getFullUrl() : string
    {
        $rootUrl = $this->getRootUrl();
        $agentName = $this->coreAgentFullName;

        return "$rootUrl/$agentName";
    }

    public function getRootUrl() : string
    {
        return config('download_url');
    }
}
