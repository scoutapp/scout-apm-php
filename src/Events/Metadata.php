<?php

// Also called AppServerLoad in other agents
//
class Metadata extends Event implements \JsonSerializable
{
    private $timer;

    public function __construct(\Scoutapm\Agent $agent)
    {
        parent::__construct($agent);

        // Construct and stop the timer to use its timestamp logic. This event
        // is a single point in time, not a range.
        $this->timer = new Timer();
        $this->timer->stop();
    }

    public function data()
    {
        return [
          "language"=> "php",
          "version"=> phpversion(),
          "server_time"=> $this->timer->getStart(),
          "framework"=> "laravel",
          "framework_version"=> "",
          "environment"=> "",
          "app_server"=> "",
          "hostname"=> gethostname(),
          "database_engine"=> "",
          "database_adapter"=> "",
          "application_name"=> "",
          "libraries"=> [],
          "paas"=> "",
          "application_root"=> "",
          "scm_subdirectory"=> "",
          "git_sha"=> "",
        ];
    }

    // TODO: Return an array of arrays: [["package name", "package version"], ....]
    public function getLibraries() : array
    {
        // $composer = require __DIR__ . "/vendor/autoload.php";
    }

    /**
     * turn this object into a list of commands to send to the CoreAgent
     *
     * @return array[core agent commands]
     */
    public function jsonSerialize() : array
    {
        $commands = [];
        $commands[] = ['ApplicationEvent' => [
            'timestamp' => $this->timer->getStart(),
            'event_value' => $this->data(),
            'event_type' => 'scout.metadata',
            'source' => 'php',
        ]];

        return $commands;
    }
}
