<?php

declare(strict_types=1);

namespace Scoutapm\Events;

use Composer\InstalledVersions;
use DateTimeImmutable;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;
use Scoutapm\Connector\Command;
use Scoutapm\Extension\ExtensionCapabilities;
use Scoutapm\Helper\DetermineHostname\DetermineHostname;
use Scoutapm\Helper\FindApplicationRoot\FindApplicationRoot;
use Scoutapm\Helper\RootPackageGitSha\FindRootPackageGitSha;
use Scoutapm\Helper\Timer;

use function array_map;
use function array_merge;
use function class_exists;
use function is_string;
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
    /** @var FindApplicationRoot */
    private $findApplicationRoot;
    /** @var DetermineHostname */
    private $determineHostname;
    /** @var FindRootPackageGitSha */
    private $findRootPackageGitSha;

    public function __construct(
        DateTimeImmutable $now,
        Config $config,
        ExtensionCapabilities $phpExtension,
        FindApplicationRoot $findApplicationRoot,
        DetermineHostname $determineHostname,
        FindRootPackageGitSha $findRootPackageGitSha
    ) {
        // Construct and stop the timer to use its timestamp logic. This event
        // is a single point in time, not a range.
        $this->timer                 = new Timer((float) $now->format('U.u'));
        $this->config                = $config;
        $this->phpExtension          = $phpExtension;
        $this->findApplicationRoot   = $findApplicationRoot;
        $this->determineHostname     = $determineHostname;
        $this->findRootPackageGitSha = $findRootPackageGitSha;
    }

    public function cleanUp(): void
    {
        unset($this->timer);
    }

    /** @return array<string, (string|VersionList|null)> */
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
            'hostname' => ($this->determineHostname)(),
            'database_engine' => '',
            'database_adapter' => '',
            'application_name' => $this->config->get(ConfigKey::APPLICATION_NAME) ?? '',
            'libraries' => $this->getLibraries(),
            'paas' => '',
            'application_root' => ($this->findApplicationRoot)(),
            'scm_subdirectory' => $this->scmSubdirectory(),
            'git_sha' => ($this->findRootPackageGitSha)(),
        ];
    }

    private function scmSubdirectory(): string
    {
        $scmSubdirectoryConfiguration = $this->config->get(ConfigKey::SCM_SUBDIRECTORY);
        if (is_string($scmSubdirectoryConfiguration) && $scmSubdirectoryConfiguration !== '') {
            return $scmSubdirectoryConfiguration;
        }

        return '';
    }

    /**
     * Return an array of arrays: [["package name", "package version"], ....]
     *
     * @return array<int, array<int, string>>
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
