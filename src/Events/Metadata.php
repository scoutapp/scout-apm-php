<?php
declare(strict_types=1);

namespace Scoutapm\Events;

use Scoutapm\Agent;
use Scoutapm\Helper\Timer;
use function gethostname;

// Also called AppServerLoad in other agents
final class Metadata extends Event implements \JsonSerializable
{
    private $timer;

    public function __construct(Agent $agent, \DateTimeImmutable $now)
    {
        parent::__construct($agent);

        // Construct and stop the timer to use its timestamp logic. This event
        // is a single point in time, not a range.
        $this->timer = new Timer((float) $now->format('U.u'));
    }

    /**
     * @return array<string, string|null|array<int, string>>
     */
    private function data()
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
            'libraries' => [],
            'paas' => '',
            'application_root' => '',
            'scm_subdirectory' => '',
            'git_sha' => '',
        ];
    }

    /**
     * @TODO: Return an array of arrays: [["package name", "package version"], ....]
     *
     * @return array<int, array<int, string>>
     */
    private function getLibraries() : array
    {
        // $composer = require __DIR__ . "/vendor/autoload.php";
        return [];
    }

    /**
     * Turn this object into a list of commands to send to the CoreAgent
     *
     * @return array<string, array<string, string|null|array>>
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
