<?php

declare(strict_types=1);

namespace Scoutapm\Events;

use Composer\InstalledVersions;
use DateTimeImmutable;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;
use Scoutapm\Connector\Command;
use Scoutapm\Extension\ExtensionCapabilities;
use Scoutapm\Helper\LocateFileOrFolder;
use Scoutapm\Helper\Timer;

use function array_key_exists;
use function array_map;
use function array_merge;
use function class_exists;
use function getenv;
use function gethostname;
use function is_array;
use function is_string;
use function method_exists;
use function sprintf;

use const PHP_VERSION;

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
    /** @var ExtensionCapabilities */
    private $phpExtension;
    /** @var LocateFileOrFolder */
    private $locateFileOrFolder;

    public function __construct(
        DateTimeImmutable $now,
        Config $config,
        ExtensionCapabilities $phpExtension,
        LocateFileOrFolder $locateFileOrFolder
    ) {
        // Construct and stop the timer to use its timestamp logic. This event
        // is a single point in time, not a range.
        $this->timer              = new Timer((float) $now->format('U.u'));
        $this->config             = $config;
        $this->phpExtension       = $phpExtension;
        $this->locateFileOrFolder = $locateFileOrFolder;
    }

    public function cleanUp(): void
    {
        unset($this->timer);
    }

    /**
     * @return array<string, (string|VersionList|null)>
     */
    private function data(): array
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

    private function applicationRoot(): string
    {
        $applicationRootConfiguration = $this->config->get(ConfigKey::APPLICATION_ROOT);
        if (is_string($applicationRootConfiguration) && $applicationRootConfiguration !== '') {
            return $applicationRootConfiguration;
        }

        $composerJsonLocation = $this->locateFileOrFolder->__invoke('composer.json');
        if ($composerJsonLocation !== null) {
            return $composerJsonLocation;
        }

        if (! array_key_exists('DOCUMENT_ROOT', $_SERVER)) {
            return '';
        }

        return $_SERVER['DOCUMENT_ROOT'];
    }

    private function scmSubdirectory(): string
    {
        $scmSubdirectoryConfiguration = $this->config->get(ConfigKey::SCM_SUBDIRECTORY);
        if (is_string($scmSubdirectoryConfiguration) && $scmSubdirectoryConfiguration !== '') {
            return $scmSubdirectoryConfiguration;
        }

        return '';
    }

    private function rootPackageGitSha(): string
    {
        $revisionShaConfiguration = $this->config->get(ConfigKey::REVISION_SHA);
        if (is_string($revisionShaConfiguration) && $revisionShaConfiguration !== '') {
            return $revisionShaConfiguration;
        }

        $herokuSlugCommit = getenv('HEROKU_SLUG_COMMIT');
        if (is_string($herokuSlugCommit) && $herokuSlugCommit !== '') {
            return $herokuSlugCommit;
        }

        if (class_exists(InstalledVersions::class) && method_exists(InstalledVersions::class, 'getRootPackage')) {
            /** @var mixed $rootPackage */
            $rootPackage = InstalledVersions::getRootPackage();
            if (is_array($rootPackage) && array_key_exists('reference', $rootPackage) && is_string($rootPackage['reference'])) {
                return $rootPackage['reference'];
            }
        }

        return '';
    }

    /**
     * Return an array of arrays: [["package name", "package version"], ....]
     *
     * @return array<int, array<int, string>>
     *
     * @psalm-return VersionList
     */
    private function getLibraries(): array
    {
        $extensionVersion = $this->phpExtension->version();

        /** @psalm-var VersionList $composerPlatformVersions */
        $composerPlatformVersions = [];
        if (class_exists(InstalledVersions::class)) {
            $composerPlatformVersions = array_map(
                static function (string $packageName): array {
                    return [
                        $packageName === 'root' ? InstalledVersions::getRootPackage()['name'] : $packageName,
                        sprintf(
                            '%s@%s',
                            (string) InstalledVersions::getPrettyVersion($packageName),
                            (string) InstalledVersions::getReference($packageName)
                        ),
                    ];
                },
                InstalledVersions::getInstalledPackages()
            );
        }

        return array_merge(
            $composerPlatformVersions,
            [['ext-scoutapm', $extensionVersion === null ? 'not installed' : $extensionVersion->toString()]]
        );
    }

    /**
     * Turn this object into a list of commands to send to the CoreAgent
     *
     * @return array<string, array<string, (string|array|null)>>
     */
    public function jsonSerialize(): array
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
