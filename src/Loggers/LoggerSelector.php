<?php

declare(strict_types=1);

namespace Scoutapm\Loggers;

use Monolog\Handler\HandlerInterface;
use Monolog\Logger as MonologLogger;
use Psr\Log\LoggerInterface;
use function class_exists;

class LoggerSelector
{
    /**
     * @param HandlerInterface[] $handlers
     * @param callable[]         $processors
     */
    public function __invoke(string $name, array $handlers = [], array $processors = []) : LoggerInterface
    {
        if (class_exists(MonologLogger::class)) {
            return new MonologLogger($name, $handlers, $processors);
        }

        return new Logger($name);
    }
}
