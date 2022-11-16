<?php

declare(strict_types=1);

namespace Scoutapm\Laravel\Queue;

use Exception;
use Illuminate\Queue\Events\JobProcessing;
use Scoutapm\Events\Span\SpanReference;
use Scoutapm\ScoutApmAgent;

use function class_basename;
use function sprintf;

final class JobQueueListener
{
    /** @var ScoutApmAgent */
    private $agent;

    public function __construct(ScoutApmAgent $agent)
    {
        $this->agent = $agent;
    }

    public function startNewRequestForJob(): void
    {
        $this->agent->startNewRequest();
    }

    /** @throws Exception */
    public function startSpanForJob(JobProcessing $jobProcessingEvent): void
    {
        $jobName = class_basename($jobProcessingEvent->job->resolveName());

        if ($this->agent->ignored($jobName)) {
            $this->agent->ignore($jobName);
        }

        /** @noinspection UnusedFunctionResultInspection */
        $this->agent->startSpan(sprintf(
            '%s/%s',
            SpanReference::INSTRUMENT_JOB,
            $jobName
        ));
    }

    /** @throws Exception */
    public function stopSpanForJob(): void
    {
        $this->agent->stopSpan();
    }

    /** @throws Exception */
    public function sendRequestForJob(): void
    {
        $this->agent->connect();
        $this->agent->send();
    }
}
