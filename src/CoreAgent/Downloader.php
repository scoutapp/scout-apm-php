<?php

declare(strict_types=1);

namespace Scoutapm\CoreAgent;

use PharData;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use Webmozart\Assert\Assert;

use function basename;
use function copy;
use function dirname;
use function fclose;
use function file_exists;
use function filectime;
use function fopen;
use function is_dir;
use function is_resource;
use function mkdir;
use function sprintf;
use function str_replace;
use function time;
use function unlink;

/**
 * A helper class for the AutomaticDownloadAndLaunchManager that handles downloading, verifying,
 * and unpacking the CoreAgent.
 *
 * @internal
 */
class Downloader
{
    /** @var string */
    private $coreAgentDir;

    /** @var string */
    private $coreAgentFullName;

    /** @var LoggerInterface */
    private $logger;

    /** @var int */
    private $staleDownloadSeconds;

    /** @var string */
    private $packageLocation;

    /** @var string */
    private $downloadLockPath;

    /**
     * @var resource|null
     * @psalm-var resource|closed-resource|null
     */
    private $downloadLockFileDescriptor;

    /** @var string */
    private $downloadUrl;

    /** @var int */
    private $coreAgentPermissions;

    public function __construct(
        string $coreAgentDir,
        string $coreAgentFullName,
        LoggerInterface $logger,
        string $downloadUrl,
        int $coreAgentPermissions
    ) {
        $this->logger = $logger;

        $this->coreAgentDir         = $coreAgentDir;
        $this->coreAgentFullName    = $coreAgentFullName;
        $this->staleDownloadSeconds = 120;

        /**
         * To avoid issues completely with inconsistent handling of PharData::decompress() detected filenames (due to
         * the presence of `.` in the Core Agent version (and thus, the `$coreAgentFullName` value), replace `.` in the
         * filename with underscores.
         *
         * Otherwise, in some versions of PHP, the extracted tar name is:
         *
         *     scout_apm_core-v1.tar
         *
         * Instead of the expected:
         *
         *     scout_apm_core-v1.2.1-x86_64-unknown-linux-gnu.tar
         *
         * @link https://bugs.php.net/bug.php?id=58852
         */
        $this->packageLocation      = $coreAgentDir . '/' . str_replace('.', '_', $coreAgentFullName) . '.tgz';
        $this->downloadLockPath     = $coreAgentDir . '/download.lock';
        $this->downloadUrl          = $downloadUrl;
        $this->coreAgentPermissions = $coreAgentPermissions;
    }

    public function download(): void
    {
        $this->createCoreAgentDir();
        $this->obtainDownloadLock();

        if ($this->downloadLockFileDescriptor === null) {
            return;
        }

        try {
            $this->downloadPackage();
            $this->untar();
        } catch (Throwable $e) {
            $this->logger->error(
                sprintf('Exception raised while downloading Core Agent: %s', $e->getMessage()),
                ['exception' => $e]
            );
        } finally {
            $this->releaseDownloadLock();
        }
    }

    private function createCoreAgentDir(): void
    {
        $recursive   = true;
        $destination = $this->coreAgentDir;

        try {
            if (
                ! is_dir($destination)
                && ! mkdir($destination, $this->coreAgentPermissions, $recursive)
                && ! is_dir($destination)
            ) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $destination));
            }
        } catch (Throwable $e) {
            $this->logger->error(
                sprintf('Failed to create directory "%s": %s', $destination, $e->getMessage()),
                ['exception' => $e]
            );
        }
    }

    private function obtainDownloadLock(): void
    {
        $this->cleanStaleDownloadLock();

        try {
            $this->downloadLockFileDescriptor = fopen($this->downloadLockPath, 'xb+');
        } catch (Throwable $e) {
            $this->logger->debug(
                sprintf('Could not obtain download lock on "%s": %s', $this->downloadLockPath, $e->getMessage()),
                ['exception' => $e]
            );
            $this->downloadLockFileDescriptor = null;
        }
    }

    private function cleanStaleDownloadLock(): void
    {
        if (! file_exists($this->downloadLockPath)) {
            $this->logger->debug(sprintf('Lock path "%s" did not exist, nothing to clean', $this->downloadLockPath));

            return;
        }

        try {
            Assert::fileExists($this->downloadLockPath);
            Assert::file($this->downloadLockPath);

            $delta = time() - filectime($this->downloadLockPath);
            if ($delta > $this->staleDownloadSeconds) {
                $this->logger->debug(sprintf('Clearing stale download lock file "%s".', $this->downloadLockPath));
                unlink($this->downloadLockPath);
            }
        } catch (Throwable $e) {
            $this->logger->debug(
                sprintf(
                    'Failed to clean stale download lock on "%s": %s',
                    $this->downloadLockPath,
                    $e->getMessage()
                ),
                ['exception' => $e]
            );
        }
    }

    private function releaseDownloadLock(): void
    {
        if ($this->downloadLockFileDescriptor === null || ! is_resource($this->downloadLockFileDescriptor)) {
            return;
        }

        fclose($this->downloadLockFileDescriptor);
        unlink($this->downloadLockPath);
    }

    private function downloadPackage(): void
    {
        $fullUrl = $this->fullUrl();

        $this->logger->debug(sprintf('Downloading package from "%s" to "%s"', $fullUrl, $this->packageLocation));

        copy($fullUrl, $this->packageLocation);

        if (! file_exists($this->packageLocation)) {
            throw new RuntimeException(sprintf(
                'Downloaded file did not exist (tried downloading %s to %s)',
                $fullUrl,
                $this->packageLocation
            ));
        }
    }

    private function untar(): void
    {
        $tgzFilename = $this->packageLocation;
        $destination = $this->coreAgentDir;
        $tarFilename = dirname($tgzFilename) . '/' . basename($tgzFilename, '.tgz') . '.tar';

        $this->logger->debug(sprintf('Decompressing archive "%s" to "%s"', $tgzFilename, $tarFilename));

        (new PharData($tgzFilename))->decompress();

        if (! file_exists($tarFilename)) {
            throw new RuntimeException(sprintf(
                'Failed to extract tar file "%s" from downloaded archive "%s"',
                $tarFilename,
                $tgzFilename
            ));
        }

        $this->logger->debug(sprintf('Extracting "%s" to path "%s"', $tarFilename, $destination));

        (new PharData($tarFilename))->extractTo($destination);
    }

    /**
     * The URL to download the agent package from
     */
    private function fullUrl(): string
    {
        return $this->downloadUrl . '/' . $this->coreAgentFullName . '.tgz';
    }
}
