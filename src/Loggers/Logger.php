<?php

declare(strict_types=1);

namespace Scoutapm\Loggers;

use Psr\Log\LoggerInterface;
use function fclose;
use function fopen;
use function fwrite;
use function preg_replace;
use function print_r;
use function sprintf;
use function time;

class Logger implements LoggerInterface
{
    /** @var string */
    private $name;

    /** @var string */
    private $logPath;

    public function __construct(string $name, ?string $logPath = null)
    {
        if ($logPath === null) {
            $alphaName = preg_replace('/[^A-Za-z0-9 ]/', '', $name);
            $timestamp = time();
            $logPath   = sprintf('scout-%s-%s.txt', $alphaName, $timestamp);
        }

        $this->name    = $name;
        $this->logPath = $logPath;
    }

    public function getName() : string
    {
        return $this->name;
    }

    //phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint

    /**
     * @param string  $message
     * @param mixed[] $context
     */
    public function emergency($message, array $context = []) : void
    {
        $this->log('EMERGENCY', $message, $context);
    }

    /**
     * @param string  $message
     * @param mixed[] $context
     */
    public function alert($message, array $context = []) : void
    {
        $this->log('ALERT', $message, $context);
    }

    /**
     * @param string  $message
     * @param mixed[] $context
     */
    public function critical($message, array $context = []) : void
    {
        $this->log('CRITICAL', $message, $context);
    }

    /**
     * @param string  $message
     * @param mixed[] $context
     */
    public function error($message, array $context = []) : void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * @param string  $message
     * @param mixed[] $context
     */
    public function warning($message, array $context = []) : void
    {
        $this->log('WARNING', $message, $context);
    }

    /**
     * @param string  $message
     * @param mixed[] $context
     */
    public function notice($message, array $context = []) : void
    {
        $this->log('NOTICE', $message, $context);
    }

    /**
     * @param string  $message
     * @param mixed[] $context
     */
    public function info($message, array $context = []) : void
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * @param string  $message
     * @param mixed[] $context
     */
    public function debug($message, array $context = []) : void
    {
        $this->log('DEBUG', $message, $context);
    }

    /**
     * @param mixed   $level
     * @param string  $message
     * @param mixed[] $context
     */
    public function log($level, $message, array $context = []) : void
    {
        $handle = fopen($this->logPath, 'ab');
        fwrite($handle, sprintf('%s: %s', $level, $message));
        fwrite($handle, print_r($context, true));
        fclose($handle);
    }
    // phpcs:enable
}
