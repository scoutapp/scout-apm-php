<?php

declare(strict_types=1);

namespace Scoutapm\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Throwable;
use Webmozart\Assert\Assert;

use function array_keys;
use function implode;
use function sprintf;
use function strtolower;

/**
 * This log decorator is used to squelch log messages below a configured threshold.
 */
final class FilteredLogLevelDecorator implements LoggerInterface
{
    use LoggerTrait;

    private const PREPEND_SCOUT_TAG = '[Scout] ';

    private const LOG_LEVEL_ORDER = [
        LogLevel::DEBUG => 0,
        LogLevel::INFO => 1,
        LogLevel::NOTICE => 2,
        LogLevel::WARNING => 3,
        LogLevel::ERROR => 4,
        LogLevel::CRITICAL => 5,
        LogLevel::ALERT => 6,
        LogLevel::EMERGENCY => 7,
    ];

    /** @var LoggerInterface */
    private $realLogger;

    /** @var int */
    private $minimumLogLevel;

    /**
     * @param string $minimumLogLevel e.g. `emergency`, `error`, etc. - {@see \Psr\Log\LogLevel}
     */
    public function __construct(LoggerInterface $realLogger, string $minimumLogLevel)
    {
        try {
            Assert::keyExists(
                self::LOG_LEVEL_ORDER,
                strtolower($minimumLogLevel),
                sprintf(
                    'Log level %s was not a valid PSR-3 compatible log level. Should be one of: %s',
                    $minimumLogLevel,
                    implode(', ', array_keys(self::LOG_LEVEL_ORDER))
                )
            );
        } catch (Throwable $e) {
            $minimumLogLevel = LogLevel::DEBUG;
            $realLogger->log(
                LogLevel::ERROR,
                $e->getMessage(),
                ['exception' => $e]
            );
        }

        $this->minimumLogLevel = self::LOG_LEVEL_ORDER[strtolower($minimumLogLevel)];
        $this->realLogger      = $realLogger;
    }

    /** {@inheritDoc} */
    public function log($level, $message, array $context = [])
    {
        if ($this->minimumLogLevel > self::LOG_LEVEL_ORDER[$level]) {
            return;
        }

        $this->realLogger->log($level, self::PREPEND_SCOUT_TAG . $message, $context);
    }
}
