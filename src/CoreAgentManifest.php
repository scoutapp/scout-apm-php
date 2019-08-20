<?php

declare(strict_types=1);

namespace Scoutapm;

use function file_get_contents;
use function json_decode;

class CoreAgentManifest
{
    /** @var string */
    public $manifest_path;

    /** @var Agent */
    public $agent;

    /** @var bool */
    public $valid;

    /** @var string */
    public $version;

    /** @var string */
    public $binVersion;

    /** @var string */
    public $binName;

    /** @var string */
    public $sha256;

    public function __construct(string $path, Agent $agent)
    {
        $this->manifest_path = $path;
        $this->agent         = $agent;

        try {
            $this->parse();
        } catch (Throwable $e) {
            $this->valid = false;
        }
    }

    public function parse() : void
    {
        $this->agent->getLogger()->info('Parsing Core Agent Manifest at ' . $this->manifest_path);

        $raw  = file_get_contents($this->manifest_path);
        $json = json_decode($raw, true); // decode the JSON into an associative array

        $this->version    = $json['version'];
        $this->binVersion = $json['core_agent_version'];
        $this->binName    = $json['core_agent_binary'];
        $this->sha256     = $json['core_agent_binary_sha256'];
        $this->valid      = true;
    }

    public function isValid() : bool
    {
        return $this->valid;
    }
}
