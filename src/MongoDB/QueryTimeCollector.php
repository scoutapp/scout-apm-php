<?php

declare(strict_types=1);

namespace Scoutapm\MongoDB;

use MongoDB\Driver\Monitoring\CommandFailedEvent;
use MongoDB\Driver\Monitoring\CommandStartedEvent;
use MongoDB\Driver\Monitoring\CommandSubscriber;
use MongoDB\Driver\Monitoring\CommandSucceededEvent;
use RuntimeException;
use Scoutapm\ScoutApmAgent;

use function extension_loaded;
use function MongoDB\Driver\Monitoring\addSubscriber;

final class QueryTimeCollector implements CommandSubscriber
{
    /** @var ScoutApmAgent */
    private $agent;

    private function __construct(ScoutApmAgent $agent)
    {
        $this->agent = $agent;
    }

    public static function register(ScoutApmAgent $agent): self
    {
        if (! extension_loaded('mongodb')) {
            throw new RuntimeException('Tried to register MongoDB subscriber, but mongodb extension was missing');
        }

        $collector = new self($agent);

        /** @psalm-suppress UnusedFunctionCall */
        addSubscriber($collector);

        return $collector;
    }

    public function commandFailed(CommandFailedEvent $event): void
    {
        $this->agent->stopSpan();
    }

    public function commandStarted(CommandStartedEvent $event): void
    {
        $activeSpan = $this->agent->startSpan('Mongo/Query/' . $event->getCommandName());

        if ($activeSpan === null) {
            return;
        }

        $activeSpan->tag('db', $event->getDatabaseName());
        $activeSpan->tag('operationId', $event->getOperationId());
        $activeSpan->tag('requestId', $event->getRequestId());
    }

    public function commandSucceeded(CommandSucceededEvent $event): void
    {
        $this->agent->stopSpan();
    }
}
