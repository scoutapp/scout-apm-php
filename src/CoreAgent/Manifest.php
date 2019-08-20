<?php

declare(strict_types=1);

namespace Scoutapm\CoreAgent;

use Psr\Log\LoggerInterface;
use Throwable;
use function file_get_contents;
use function json_decode;

/** @internal */
class Manifest
{
    /** @var string */
    public $manifest_path;

    /** @var LoggerInterface */
    public $logger;

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

    public function __construct(string $path, LoggerInterface $logger)
    {
        $this->manifest_path = $path;
        $this->logger = $logger;

        try {
            $this->parse();
        } catch (Throwable $e) {
            $this->valid = false;
        }
    }

    public function parse() : void
    {
        $this->logger->info('Parsing Core Agent Manifest at ' . $this->manifest_path);

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
