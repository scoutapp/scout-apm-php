<?php

namespace Scoutapm\Loggers;

use Psr\Log\LoggerInterface;

class LoggerSelector
{
    public function __invoke(string $name, array $handlers = [], array $processors = []) : LoggerInterface
    {
        if (class_exists('Monolog\Logger')) {
            return new \Monolog\Logger($name, $handlers, $processors);
        }

        return new Logger($name);
    }
}
