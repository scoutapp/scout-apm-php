<?php

declare(strict_types=1);

namespace Scoutapm\CoreAgent;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use const JSON_ERROR_NONE;
use function file_get_contents;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;
use function sprintf;

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
            $this->logger->debug(
                sprintf('Exception raised whilst parsing manifest: %s', $e->getMessage()),
                ['exception' => $e]
            );
            $this->valid = false;
        }
    }

    private function parse() : void
    {
        $this->logger->info(sprintf('Parsing Core Agent Manifest at "%s"', $this->manifestPath));

        $raw  = file_get_contents($this->manifestPath);
        $json = json_decode($raw, true); // decode the JSON into an associative array

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(sprintf('Decoded JSON was null, last JSON error: %s', json_last_error_msg()));
        }

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
