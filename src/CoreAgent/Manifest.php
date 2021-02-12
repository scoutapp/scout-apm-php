<?php

declare(strict_types=1);

namespace Scoutapm\CoreAgent;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use Webmozart\Assert\Assert;

use function file_get_contents;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;
use function sprintf;

use const JSON_ERROR_NONE;

/** @internal */
class Manifest
{
    /** @var string */
    private $manifestPath;

    /** @var LoggerInterface */
    private $logger;

    /** @var bool */
    private $valid;

    /** @var string|null */
    private $binVersion;

    /** @var string|null */
    private $binName;

    /** @var string|null */
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

    private function parse(): void
    {
        $this->logger->info(sprintf('Parsing Core Agent Manifest at "%s"', $this->manifestPath));

        Assert::fileExists($this->manifestPath);
        Assert::file($this->manifestPath);
        Assert::readable($this->manifestPath);

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

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function hashOfBinary(): string
    {
        Assert::string($this->sha256);

        return $this->sha256;
    }

    public function binaryName(): string
    {
        Assert::string($this->binName);

        return $this->binName;
    }

    public function binaryVersion(): string
    {
        Assert::stringNotEmpty($this->binVersion);

        return $this->binVersion;
    }
}
