<?php

declare(strict_types=1);

namespace Scoutapm\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Webmozart\Assert\Assert;
use function strtolower;

/**
 * This log decorator is used to squelch log messages below a configured threshold.
 */
final class FilteredLogLevelDecorator implements LoggerInterface
{
    use LoggerTrait;

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
        Assert::keyExists(self::LOG_LEVEL_ORDER, $minimumLogLevel);

        $this->minimumLogLevel = self::LOG_LEVEL_ORDER[strtolower($minimumLogLevel)];
        $this->realLogger      = $realLogger;
    }

    /** {@inheritDoc} */
    public function log($level, $message, array $context = [])
    {
        if ($this->minimumLogLevel > self::LOG_LEVEL_ORDER[$level]) {
            return;
        }

        $this->realLogger->log($level, $message, $context);
    }
}
