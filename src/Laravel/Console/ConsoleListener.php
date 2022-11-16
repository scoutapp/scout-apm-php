<?php

declare(strict_types=1);

namespace Scoutapm\Laravel\Console;

use Exception;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Scoutapm\Events\Span\SpanReference;
use Scoutapm\Events\Tag\Tag;
use Scoutapm\ScoutApmAgent;

use function implode;
use function sprintf;

final class ConsoleListener
{
    /** @var ScoutApmAgent */
    private $agent;
    /** @var list<string> */
    private $argv;

    /** @param list<string> $argv */
    public function __construct(ScoutApmAgent $agent, array $argv)
    {
        $this->agent = $agent;
        $this->argv  = $argv;
    }

    /** @throws Exception */
    public function startSpanForCommand(CommandStarting $commandStartingEvent): void
    {
        if ($commandStartingEvent->command === null) {
            return;
        }

        $commandName = $commandStartingEvent->command;

        $this->agent->startNewRequest();

        if ($this->agent->ignored($commandName)) {
            $this->agent->ignore($commandName);
        }

        $this->agent->addContext(Tag::TAG_ARGUMENTS, implode(' ', $this->argv));

        /** @noinspection UnusedFunctionResultInspection */
        $this->agent->startSpan(sprintf(
            '%s/artisan/%s',
            SpanReference::INSTRUMENT_JOB,
            $commandName
        ));
    }

    /** @throws Exception */
    public function stopSpanForCommand(CommandFinished $commandFinishedEvent): void
    {
        if ($commandFinishedEvent->command === null) {
            return;
        }

        $this->agent->stopSpan();
        $this->agent->connect();
        $this->agent->send();
    }
}
