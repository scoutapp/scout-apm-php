<?php

namespace Scoutapm\Coreagent;

class Manifest
{
    private $path;
    private $version;
    private $binaryName;
    private $binaryVersion;
    private $hash;
    private $valid = false;

    public function __construct(string $path)
    {
        $this->path = $path;

        $this->parse();
    }

    public function parse()
    {
        echo "Path: $this->path \n";
        try {
            $handle = fopen($this->path, 'r');
            $data = fread($handle, filesize($this->path));
            $json = json_decode($data, true);
        }
        catch (\Exception $exception) {
            $this->valid = false;
            return;
        }

        $this->version = $json['version'];
        $this->binaryVersion = $json['core_agent_version'];
        $this->binaryName = $json['core_agent_binary'];
        $this->hash = $json['core_agent_binary_sha256'];
        $this->valid = true;
    }

    public function isValid() : bool
    {
        return $this->valid;
    }

    public function getBinaryName() : string
    {
        return $this->binaryName;
    }

    public function getBinaryVersion() : string
    {
        return $this->binaryVersion;
    }

    public function getHash() : string
    {
        return $this->hash;
    }
}
