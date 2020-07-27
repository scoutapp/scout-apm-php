<?php

declare(strict_types=1);

namespace Scoutapm\Events;

use DateTimeImmutable;
use PackageVersions\Versions;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;
use Scoutapm\Connector\Command;
use Scoutapm\Extension\ExtentionCapabilities;
use Scoutapm\Helper\Timer;
use const PHP_VERSION;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function dirname;
use function explode;
use function file_exists;
use function getenv;
use function gethostname;
use function is_readable;
use function is_string;
use function realpath;

/**
 * Also called AppServerLoad in other agents
 *
 * @internal
 *
 * @psalm-type VersionList = list<array{0: string, 1: string}>
 */
final class Metadata implements Command
{
    /** @var Timer */
    private $timer;
    /** @var Config */
    private $config;
    /** @var ExtentionCapabilities */
    private $phpExtension;

    public function __construct(DateTimeImmutable $now, Config $config, ExtentionCapabilities $phpExtension)
    {
        // Construct and stop the timer to use its timestamp logic. This event
        // is a single point in time, not a range.
        $this->timer        = new Timer((float) $now->format('U.u'));
        $this->config       = $config;
        $this->phpExtension = $phpExtension;
    }

    public function cleanUp() : void
    {
        unset($this->timer);
    }

    /**
     * @return array<string, (string|VersionList|null)>
     */
    private function data() : array
    {
        return [
            'language' => 'php',
            'version' => PHP_VERSION,
            'language_version' => PHP_VERSION,
            'server_time' => $this->timer->getStart(),
            'framework' => $this->config->get(ConfigKey::FRAMEWORK) ?? '',
            'framework_version' => $this->config->get(ConfigKey::FRAMEWORK_VERSION) ?? '',
            'environment' => '',
            'app_server' => '',
            'hostname' => $this->config->get(ConfigKey::HOSTNAME) ?? gethostname(),
            'database_engine' => '',
            'database_adapter' => '',
            'application_name' => $this->config->get(ConfigKey::APPLICATION_NAME) ?? '',
            'libraries' => $this->getLibraries(),
            'paas' => '',
            'application_root' => $this->applicationRoot(),
            'scm_subdirectory' => $this->scmSubdirectory(),
            'git_sha' => $this->rootPackageGitSha(),
        ];
    }

    /**
     * Try to locate a file or folder in any parent directory (upwards of this library itself)
     */
    private function locateFileOrFolder(string $fileOrFolder) : ?string
    {
        // Starting 3 levels up will avoid finding scout-apm-php's own contents
        $dir        = dirname(__DIR__, 3);
        $rootOrHome = '/';

        while (dirname($dir) !== $dir && $dir !== $rootOrHome) {
            $fileOrFolderAttempted = $dir . '/' . $fileOrFolder;
            if (file_exists($fileOrFolderAttempted) && is_readable($fileOrFolderAttempted)) {
                return realpath($dir);
            }
            $dir = dirname($dir);
        }

        return null;
    }

    private function applicationRoot() : string
    {
        $applicationRootConfiguration = $this->config->get(ConfigKey::APPLICATION_ROOT);
        if (is_string($applicationRootConfiguration) && $applicationRootConfiguration !== '') {
            return $applicationRootConfiguration;
        }

        $composerJsonLocation = $this->locateFileOrFolder('composer.json');
        if ($composerJsonLocation !== null) {
            return $composerJsonLocation;
        }

        if (! array_key_exists('DOCUMENT_ROOT', $_SERVER)) {
            return '';
        }

        return $_SERVER['DOCUMENT_ROOT'];
    }

    private function scmSubdirectory() : string
    {
        $scmSubdirectoryConfiguration = $this->config->get(ConfigKey::SCM_SUBDIRECTORY);
        if (is_string($scmSubdirectoryConfiguration) && $scmSubdirectoryConfiguration !== '') {
            return $scmSubdirectoryConfiguration;
        }

        return '';
    }

    private function rootPackageGitSha() : string
    {
        $revisionShaConfiguration = $this->config->get(ConfigKey::REVISION_SHA);
        if (is_string($revisionShaConfiguration) && $revisionShaConfiguration !== '') {
            return $revisionShaConfiguration;
        }

        $herokuSlugCommit = getenv('HEROKU_SLUG_COMMIT');
        if (is_string($herokuSlugCommit) && $herokuSlugCommit !== '') {
            return $herokuSlugCommit;
        }

        return explode('@', Versions::getVersion(Versions::ROOT_PACKAGE_NAME))[1];
    }

    /**
     * Return an array of arrays: [["package name", "package version"], ....]
     *
     * @return array<int, array<int, string>>
     *
     * @psalm-return VersionList
     */
    private function getLibraries() : array
    {
        $extensionVersion = $this->phpExtension->version();

        /** @psalm-var VersionList $composerPlatformVersions */
        $composerPlatformVersions = array_map(
        /** @return string[][]|array<int, string> */
            static function (string $package, string $version) : array {
                return [$package, $version];
            },
            array_keys(Versions::VERSIONS),
            Versions::VERSIONS
        );

        return array_values(array_merge(
            $composerPlatformVersions,
            [['ext-scoutapm', $extensionVersion === null ? 'not installed' : $extensionVersion->toString()]]
        ));
    }

    /**
     * Turn this object into a list of commands to send to the CoreAgent
     *
     * @return array<string, array<string, (string|array|null)>>
     */
    public function jsonSerialize() : array
    {
        return [
            'ApplicationEvent' => [
                'timestamp' => $this->timer->getStart(),
                'event_value' => $this->data(),
                'event_type' => 'scout.metadata',
                'source' => 'php',
            ],
        ];
    }
}
