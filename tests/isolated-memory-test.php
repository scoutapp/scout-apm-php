<?php

declare(strict_types=1);

use Scoutapm\Agent;
use Scoutapm\Config;
use Scoutapm\Events\Span\SpanReference;
use Scoutapm\Extension\PotentiallyAvailableExtensionCapabilities;
use Scoutapm\UnitTests\TestLogger;

require __DIR__ . '/../vendor/autoload.php';

$scoutApmKey = getenv('SCOUT_APM_KEY');
$runCount    = (int) getenv('RUN_COUNT');

if ($scoutApmKey === false || $scoutApmKey === '' || $runCount <= 0) {
    echo "Try running: \n\n  SCOUT_APM_KEY=abc123 RUN_COUNT=100 php tests/isolated-memory-test.php\n\n";

    throw new RuntimeException('Set the environment variable SCOUT_APM_KEY to enable this test.');
}

$config = Config::fromArray([
    'name' => 'Agent Integration Test',
    'key' => $scoutApmKey,
    'monitor' => true,
]);

$logger = new TestLogger();

$agent = Agent::fromConfig($config, $logger);

$agent->connect();

(new PotentiallyAvailableExtensionCapabilities())->clearRecordedCalls();

$tagSize        = 500000;
$startingMemory = memory_get_usage();
for ($i = 1; $i <= $runCount; $i++) {
    $agent->startNewRequest();
    $span = $agent->startSpan(sprintf(
        '%s/%s%d',
        SpanReference::INSTRUMENT_JOB,
        'Test Job #',
        $i
    ));

    assert($span !== null);

    $span->tag('something', str_repeat('a', $tagSize));

    $agent->stopSpan();

    $agent->connect();
    $agent->send();
}

$logger->records        = [];
$logger->recordsByLevel = [];

$used = memory_get_usage() - $startingMemory;
echo $used . "\n";
