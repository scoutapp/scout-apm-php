<?php

declare(strict_types=1);

namespace Scoutapm\Events;

use DateTimeImmutable;
use PackageVersions\Versions;
use Scoutapm\Connector\Command;
use Scoutapm\Helper\Timer;
use const PHP_VERSION;
use function array_keys;
use function array_map;
use function explode;
use function gethostname;

/**
 * Also called AppServerLoad in other agents
 *
 * @internal
 */
final class Metadata implements Command
{
    /** @var Timer */
    private $timer;

    public function __construct(DateTimeImmutable $now)
    {
        // Construct and stop the timer to use its timestamp logic. This event
        // is a single point in time, not a range.
        $this->timer = new Timer((float) $now->format('U.u'));
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
            'application_root' => '',
            'scm_subdirectory' => '',
            'git_sha' => $this->rootPackageGitSha(),
        ];
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
