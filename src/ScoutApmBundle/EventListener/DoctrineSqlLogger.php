<?php

declare(strict_types=1);

namespace Scoutapm\ScoutApmBundle\EventListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\LoggerChain;
use Doctrine\DBAL\Logging\SQLLogger;
use Scoutapm\Events\Span\SpanReference;
use Scoutapm\ScoutApmAgent;

final class DoctrineSqlLogger implements SQLLogger
{
    /** @var ScoutApmAgent */
    private $agent;

    /** @var SpanReference|null */
    private $currentSpan;

    public function __construct(ScoutApmAgent $agent)
    {
        $this->agent = $agent;
    }

    public function registerWith(Connection $connection): void
    {
        $connectionConfiguration = $connection->getConfiguration();

        $currentLogger = $connectionConfiguration->getSQLLogger();

        if ($currentLogger === null) {
            $connectionConfiguration->setSQLLogger($this);

            return;
        }

        $connectionConfiguration->setSQLLogger(new LoggerChain([
            $currentLogger,
            $this,
        ]));
    }

    /** @inheritDoc */
    public function startQuery($sql, ?array $params = null, ?array $types = null)
    {
        $this->currentSpan = $this->agent->startSpan('SQL/Query', null, true);

        if ($this->currentSpan === null) {
            return;
        }

        $this->currentSpan->tag('db.statement', $sql);
    }

    /** @inheritDoc */
    public function stopQuery()
    {
        if ($this->currentSpan === null) {
            return;
        }

        $this->agent->stopSpan();
        $this->currentSpan = null;
    }
}
