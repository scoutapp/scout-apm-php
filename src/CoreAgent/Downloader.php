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
    private $stale_download_secs;

    /** @var string */
    private $package_location;

    /** @var string */
    private $download_lock_path;

    /**
     * @var resource|null
     * @psalm-var resource|closed-resource|null
     */
    private $download_lock_fd;

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

        $this->coreAgentDir        = $coreAgentDir;
        $this->coreAgentFullName   = $coreAgentFullName;
        $this->stale_download_secs = 120;

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
        $this->package_location     = $coreAgentDir . '/' . str_replace('.', '_', $coreAgentFullName) . '.tgz';
        $this->download_lock_path   = $coreAgentDir . '/download.lock';
        $this->downloadUrl          = $downloadUrl;
        $this->coreAgentPermissions = $coreAgentPermissions;
    }

    public function download() : void
    {
        $this->createCoreAgentDir();
        $this->obtainDownloadLock();

        if ($this->download_lock_fd === null) {
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

    private function createCoreAgentDir() : void
    {
        $recursive   = true;
        $destination = $this->coreAgentDir;

        try {
            if (! is_dir($destination)
                && ! mkdir($destination, $this->coreAgentPermissions, $recursive)
                && ! is_dir($destination)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $destination));
            }
        } catch (Throwable $e) {
            $this->logger->error(
                sprintf('Failed to create directory "%s": %s', $destination, $e->getMessage()),
                ['exception' => $e]
            );
        }
    }

    private function obtainDownloadLock() : void
    {
        $this->cleanStaleDownloadLock();

        try {
            $this->download_lock_fd = fopen($this->download_lock_path, 'xb+');
        } catch (Throwable $e) {
            $this->logger->debug(
                sprintf('Could not obtain download lock on "%s": %s', $this->download_lock_path, $e->getMessage()),
                ['exception' => $e]
            );
            $this->download_lock_fd = null;
        }
    }

    private function cleanStaleDownloadLock() : void
    {
        if (! file_exists($this->download_lock_path)) {
            $this->logger->debug(sprintf('Lock path "%s" did not exist, nothing to clean', $this->download_lock_path));

            return;
        }

        try {
            Assert::fileExists($this->download_lock_path);
            Assert::file($this->download_lock_path);

            $delta = time() - filectime($this->download_lock_path);
            if ($delta > $this->stale_download_secs) {
                $this->logger->debug(sprintf('Clearing stale download lock file "%s".', $this->download_lock_path));
                unlink($this->download_lock_path);
            }
        } catch (Throwable $e) {
            $this->logger->debug(
                sprintf(
                    'Failed to clean stale download lock on "%s": %s',
                    $this->download_lock_path,
                    $e->getMessage()
                ),
                ['exception' => $e]
            );
        }
    }

    private function releaseDownloadLock() : void
    {
        if ($this->download_lock_fd === null || ! is_resource($this->download_lock_fd)) {
            return;
        }

        fclose($this->download_lock_fd);
        unlink($this->download_lock_path);
    }

    private function downloadPackage() : void
    {
        $fullUrl = $this->fullUrl();

        $this->logger->debug(sprintf('Downloading package from "%s" to "%s"', $fullUrl, $this->package_location));

        copy($fullUrl, $this->package_location);

        if (! file_exists($this->package_location)) {
            throw new RuntimeException(sprintf(
                'Downloaded file did not exist (tried downloading %s to %s)',
                $fullUrl,
                $this->package_location
            ));
        }
    }

    private function untar() : void
    {
        $tgzFilename = $this->package_location;
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
    private function fullUrl() : string
    {
        return $this->downloadUrl . '/' . $this->coreAgentFullName . '.tgz';
    }
}
