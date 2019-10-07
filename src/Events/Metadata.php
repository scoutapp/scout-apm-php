<?php

declare(strict_types=1);

namespace Scoutapm\Events;

use DateTimeImmutable;
use PackageVersions\Versions;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;
use Scoutapm\Connector\Command;
use Scoutapm\Helper\Timer;
use const PHP_VERSION;
use function array_key_exists;
use function array_keys;
use function array_map;
use function dirname;
use function explode;
use function file_exists;
use function gethostname;
use function is_file;
use function is_readable;
use function is_string;
use function realpath;

/**
 * Also called AppServerLoad in other agents
 *
 * @internal
 */
final class Metadata implements Command
{
    /** @var Timer */
    private $timer;

    /** @var Config */
    private $config;

    public function __construct(DateTimeImmutable $now, Config $config)
    {
        // Construct and stop the timer to use its timestamp logic. This event
        // is a single point in time, not a range.
        $this->timer  = new Timer((float) $now->format('U.u'));
        $this->config = $config;
    }

    /**
     * @return array<string, (string|array<int, array<int, string>>|null)>
     */
    private function data() : array
    {
        return [
            'language' => 'php',
            'version' => PHP_VERSION,
            'server_time' => $this->timer->getStart(),
            'framework' => 'laravel',
            'framework_version' => '',
            'environment' => '',
            'app_server' => '',
            'hostname' => gethostname(),
            'database_engine' => '',
            'database_adapter' => '',
            'application_name' => '',
            'libraries' => $this->getLibraries(),
            'paas' => '',
            'application_root' => $this->applicationRoot(),
            'scm_subdirectory' => '',
            'git_sha' => $this->rootPackageGitSha(),
        ];
    }

    /**
     * Try to locate composer.json in any parent directory; it's usually a good sign of where the application root is.
     */
    private function locateComposerJson() : ?string
    {
        // Starting 3 levels up will avoid finding scout-apm-php's own composer.json
        $dir        = dirname(__DIR__, 3);
        $rootOrHome = '/';

        while (dirname($dir) !== $dir && $dir !== $rootOrHome) {
            $composerAttempted = $dir . '/composer.json';
            if (file_exists($composerAttempted) && is_file($composerAttempted) && is_readable($composerAttempted)) {
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

        $composerJsonLocation = $this->locateComposerJson();
        if ($composerJsonLocation !== null) {
            return $composerJsonLocation;
        }

        if (! array_key_exists('DOCUMENT_ROOT', $_SERVER)) {
            return '';
        }

        return $_SERVER['DOCUMENT_ROOT'];
    }

    private function rootPackageGitSha() : string
    {
        return explode('@', Versions::getVersion(Versions::ROOT_PACKAGE_NAME))[1];
    }

    /**
     * Return an array of arrays: [["package name", "package version"], ....]
     *
     * @return array<int, array<int, string>>
     */
    private function getLibraries() : array
    {
        return array_map(
            /** @return string[][]|array<int, string> */
            static function (string $package, string $version) : array {
                return [$package, $version];
            },
            array_keys(Versions::VERSIONS),
            Versions::VERSIONS
        );
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
