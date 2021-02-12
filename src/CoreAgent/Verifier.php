<?php

declare(strict_types=1);

namespace Scoutapm\CoreAgent;

use Psr\Log\LoggerInterface;

use function hash_equals;
use function hash_file;

/** @internal */
class Verifier
{
    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $coreAgentDownloadPath;

    public function __construct(LoggerInterface $logger, string $coreAgentDownloadPath)
    {
        $this->logger                = $logger;
        $this->coreAgentDownloadPath = $coreAgentDownloadPath;
    }

    public function verify(): ?string
    {
        // Check for a well formed manifest
        $manifest = new Manifest($this->coreAgentDownloadPath . '/manifest.json', $this->logger);
        if (! $manifest->isValid()) {
            $this->logger->debug('Core Agent verification failed: Manifest is not valid.');

            return null;
        }

        // Check that the hash matches
        $binPath = $this->coreAgentDownloadPath . '/' . $manifest->binaryName();
        if (hash_equals($manifest->hashOfBinary(), hash_file('sha256', $binPath))) {
            return $binPath;
        }

        $this->logger->debug('Core Agent verification failed: SHA mismatch.');

        return null;
    }
}
