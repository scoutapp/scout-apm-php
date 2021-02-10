<?php

declare(strict_types=1);

namespace Scoutapm\IntegrationTests;

use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;
use PHPUnit\Framework\TestSuite;

use function getenv;
use function sprintf;
use function str_repeat;
use function strlen;
use function substr;

final class CheckScoutApmKeyListener implements TestListener
{
    /** @var bool */
    private $hasOutput = false;

    private const SHOW_CHARACTERS_OF_KEY = 2;
    use TestListenerDefaultImplementation;

    public function startTestSuite(TestSuite $suite): void
    {
        if ($this->hasOutput) {
            return;
        }

        $scoutApmKey = getenv('SCOUT_APM_KEY');

        if ($scoutApmKey === false || $scoutApmKey === '') {
            echo "Running without SCOUT_APM_KEY configured, some tests will be skipped.\n\n";
            $this->hasOutput = true;

            return;
        }

        echo sprintf(
            "Running with SCOUT_APM_KEY set %s%s\n\n",
            substr($scoutApmKey, 0, self::SHOW_CHARACTERS_OF_KEY),
            str_repeat('*', strlen($scoutApmKey) - self::SHOW_CHARACTERS_OF_KEY)
        );
        $this->hasOutput = true;
    }
}
