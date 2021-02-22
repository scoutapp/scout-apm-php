<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\ScoutApmBundle\EventListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\LoggerChain;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\ORM\Configuration;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Scoutapm\Events\Span\Span;
use Scoutapm\Events\Span\SpanReference;
use Scoutapm\ScoutApmAgent;
use Scoutapm\ScoutApmBundle\EventListener\DoctrineSqlLogger;

/** @covers \Scoutapm\ScoutApmBundle\EventListener\DoctrineSqlLogger */
final class DoctrineSqlLoggerTest extends TestCase
{
    /** @var ScoutApmAgent&MockObject */
    private $agent;
    /** @var DoctrineSqlLogger */
    private $sqlLogger;

    public function setUp(): void
    {
        parent::setUp();

        $this->agent = $this->createMock(ScoutApmAgent::class);

        $this->sqlLogger = new DoctrineSqlLogger($this->agent);
    }

    public function testRegisterInjectsSqlLoggerAsChainWhenLoggerAlreadySet(): void
    {
        $configuration = new Configuration();
        $configuration->setSQLLogger($this->createMock(SQLLogger::class));

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('getConfiguration')
            ->willReturn($configuration);

        $this->sqlLogger->registerWith($connection);

        self::assertInstanceOf(LoggerChain::class, $configuration->getSQLLogger());
    }

    public function testRegisterAddsSqlLoggerWhenNoLoggerHasBeenSet(): void
    {
        $configuration = new Configuration();

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('getConfiguration')
            ->willReturn($configuration);

        $this->sqlLogger->registerWith($connection);

        self::assertSame($this->sqlLogger, $configuration->getSQLLogger());
    }

    public function testStartQueryStartsAgentSpanAndTagsQuery(): void
    {
        $span = $this->createMock(Span::class);
        $span->expects(self::once())
            ->method('tag')
            ->with('db.statement', 'SELECT * FROM great_table');

        $this->agent->expects(self::once())
            ->method('startSpan')
            ->with('SQL/Query')
            ->willReturn(SpanReference::fromSpan($span));

        $this->sqlLogger->startQuery('SELECT * FROM great_table', [], []);
    }

    public function testStopQueryStopsQueryWhenSpanWasStarted(): void
    {
        $span = $this->createMock(Span::class);
        $span->expects(self::once())
            ->method('tag')
            ->with('db.statement', 'SELECT * FROM great_table');

        $this->agent->expects(self::once())
            ->method('startSpan')
            ->with('SQL/Query')
            ->willReturn(SpanReference::fromSpan($span));

        $this->agent->expects(self::once())
            ->method('stopSpan');

        $this->sqlLogger->startQuery('SELECT * FROM great_table', [], []);

        $this->sqlLogger->stopQuery();
    }

    public function testStopQueryDoesNotStopSpanIfStartSpanReturnedNull(): void
    {
        $this->agent->expects(self::once())
            ->method('startSpan')
            ->with('SQL/Query')
            ->willReturn(null);

        $this->agent->expects(self::never())
            ->method('stopSpan');

        $this->sqlLogger->startQuery('SELECT * FROM great_table', [], []);

        $this->sqlLogger->stopQuery();
    }

    public function testStopQueryDoesNothingIfSpanWasNotStarted(): void
    {
        $this->agent->expects(self::never())
            ->method('stopSpan');

        $this->sqlLogger->stopQuery();
    }
}
