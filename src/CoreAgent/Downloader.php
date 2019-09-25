<?php

declare(strict_types=1);

namespace Scoutapm\CoreAgent;

use PharData;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use function basename;
use function copy;
use function dirname;
use function fclose;
use function file_exists;
use function filectime;
use function fopen;
use function is_dir;
use function mkdir;
use function sprintf;
use function strpos;
use function substr;
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

    /** @var resource|null */
    private $download_lock_fd;

    /** @var string */
    private $downloadUrl;

    public function __construct(string $coreAgentDir, string $coreAgentFullName, LoggerInterface $logger, string $downloadUrl)
    {
        $this->logger = $logger;

        $this->coreAgentDir        = $coreAgentDir;
        $this->coreAgentFullName   = $coreAgentFullName;
        $this->stale_download_secs = 120;

        $this->package_location   = $coreAgentDir . '/' . $coreAgentFullName . '.tgz';
        $this->download_lock_path = $coreAgentDir . '/download.lock';
        $this->downloadUrl        = $downloadUrl;
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
            $this->logger->error('Exception raised while downloading Core Agent: ' . $e);
        } finally {
            $this->releaseDownloadLock();
        }
    }

    private function createCoreAgentDir() : void
    {
        try {
            $permissions = 0777; // TODO: AgentContext.instance.config.core_agent_permissions()
            $recursive   = true;
            $destination = $this->coreAgentDir;

            if (! is_dir($destination)) {
                mkdir($destination, $permissions, $recursive);
            }
        } catch (Throwable $e) {
            $this->logger->error('Failed to create directory: ' . $destination);
        }
    }

    private function obtainDownloadLock() : void
    {
        $this->cleanStaleDownloadLock();

        try {
            $this->download_lock_fd = fopen(
                $this->download_lock_path,
                'x+' // This is the same as O_RDWR | O_EXCL | O_CREAT
                // O_RDWR | O_CREAT | O_EXCL | O_NONBLOCK
            );
        } catch (Throwable $e) {
            $this->logger->debug('Could not obtain download lock on ' . $this->download_lock_path . ': ' . $e);
            $this->download_lock_fd = null;
        }
    }

    private function cleanStaleDownloadLock() : void
    {
        try {
            $delta = time() - filectime($this->download_lock_path);
            if ($delta > $this->stale_download_secs) {
                $this->logger->debug('Clearing stale download lock file.');
                unlink($this->download_lock_path);
            }
        } catch (Throwable $e) {
            // Log this
        }
    }

    private function releaseDownloadLock() : void
    {
        if ($this->download_lock_fd === null) {
            return;
        }

        fclose($this->download_lock_fd);
        unlink($this->download_lock_path);
    }

    private function downloadPackage() : void
    {
        $fullUrl = $this->fullUrl();
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

        $packageLocationWithoutExtension = basename($tgzFilename, '.tgz');

        /**
         * `decompress()` considers anything after the first `.` as the "extension", so provide a full extension. This
         * results in a tgz filename of:
         *
         *     scout_apm_core-v1.2.1-x86_64-unknown-linux-gnu.tgz
         *
         * To be decompressed to:
         *
         *     scout_apm_core-v1.tar
         *
         * So by specifying the extension as `.2.1-x86_64-unknown-linux-gnu.tar` (instead of the default of `.tar`) to
         * the `decompress()` function, the expected output name would be used instead:
         *
         *     scout_apm_core-v1.2.1-x86_64-unknown-linux-gnu.tar
         *
         * @link https://bugs.php.net/bug.php?id=58852
         */
        (new PharData($tgzFilename))->decompress(
            substr($packageLocationWithoutExtension, strpos($packageLocationWithoutExtension, '.')) . '.tar'
        );

        $tarFilename = dirname($tgzFilename) . '/' . $packageLocationWithoutExtension . '.tar';

        if (! file_exists($tarFilename)) {
            throw new RuntimeException(sprintf(
                'Failed to extract tar file "%s" from downloaded archive "%s"',
                $tarFilename,
                $tgzFilename
            ));
        }

        // Extract it to destination
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
