<?php

/**
 * @see \Scoutapm\IntegrationTests\AgentTest::testUncaughtErrorsAreCapturedAndSentToScout
 *
 * This file exists because exceptions cannot be left uncaught in PHP Unit, so we run this as an external
 * script which throws an exception.
 */

declare(strict_types=1);

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Scoutapm\Agent;
use Scoutapm\Config;

require __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', '0');

$scoutApmKey = getenv('SCOUT_APM_KEY');

if ($scoutApmKey === false || $scoutApmKey === '') {
    echo "Try running: \n\n  SCOUT_APM_KEY=abc123 php tests/isolated-error-capture-test.php\n\n";

    throw new RuntimeException('Set the environment variable SCOUT_APM_KEY to enable this test.');
}

$config = Config::fromArray([
    Config\ConfigKey::APPLICATION_NAME => 'Agent Integration Test',
    Config\ConfigKey::APPLICATION_KEY => $scoutApmKey,
    Config\ConfigKey::MONITORING_ENABLED => true,
    Config\ConfigKey::ERRORS_ENABLED => true,
]);

$logFile = sys_get_temp_dir() . '/' . uniqid('scout-error-capture-test-', true) . '.log';

echo $logFile . "\n";

$logger = new Logger('scout-error-capture-test');
$logger->pushHandler(new StreamHandler($logFile));

$agent = Agent::fromConfig($config, $logger);

$agent->connect();

$agent->tagRequest('myTag', 'myTagValue');

$_SERVER['HTTP_HOST']   = 'my-test-site';
$_SERVER['SERVER_PORT'] = '443';
$_SERVER['HTTPS']       = 'on';
$_SERVER['REQUEST_URI'] = '/path/to/my/app';

$agent->webTransaction('MyWebTransaction', static function (): void {
    throw new LogicException('Something went wrong');
});
