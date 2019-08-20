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
    private $manifestPath;

    /** @var LoggerInterface */
    private $logger;

    /** @var bool */
    private $valid;

    /** @var string */
    private $binVersion;

    /** @var string */
    private $binName;

    /** @var string */
    private $sha256;

    public function __construct(string $manifestPath, LoggerInterface $logger)
    {
        $this->manifestPath = $manifestPath;
        $this->logger       = $logger;

        try {
            $this->parse();
        } catch (Throwable $e) {
            $this->valid = false;
        }
    }

    private function parse() : void
    {
        $this->logger->info('Parsing Core Agent Manifest at ' . $this->manifestPath);

        $raw  = file_get_contents($this->manifestPath);
        $json = json_decode($raw, true); // decode the JSON into an associative array

        // @todo unused, do we need this?
        //$this->version    = $json['version'];

        $this->binVersion = $json['core_agent_version'];
        $this->binName    = $json['core_agent_binary'];
        $this->sha256     = $json['core_agent_binary_sha256'];
        $this->valid      = true;
    }

    public function isValid() : bool
    {
        return $this->valid;
    }

    public function hashOfBinary() : string
    {
        return $this->sha256;
    }

    public function binaryName() : string
    {
        return $this->binName;
    }

    public function binaryVersion() : string
    {
        return $this->binVersion;
    }
}
