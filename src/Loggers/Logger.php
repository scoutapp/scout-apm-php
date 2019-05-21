<?php

namespace Scoutapm\Loggers;

use Psr\Log\LoggerInterface;

class Logger implements LoggerInterface
{
    private $name;

    private $logPath;

    public function __construct(string $name, ?string $logPath = null)
    {
        if ($logPath === null) {
            $alphaName = preg_replace("/[^A-Za-z0-9 ]/", '', $name);
            $timestamp = time();
            $logPath = "scout-$alphaName-$timestamp.txt";
        }

        $this->name = $name;
        $this->logPath = $logPath;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function emergency($message, array $context = array())
    {
        $this->log('EMERGENCY', $message, $context);
    }

    public function alert($message, array $context = array())
    {
        $this->log('ALERT', $message, $context);
    }

    public function critical($message, array $context = array())
    {
        $this->log('CRITICAL', $message, $context);
    }

    public function error($message, array $context = array())
    {
        $this->log('ERROR', $message, $context);
    }

    public function warning($message, array $context = array())
    {
        $this->log('WARNING', $message, $context);
    }

    public function notice($message, array $context = array())
    {
        $this->log('NOTICE', $message, $context);
    }

    public function info($message, array $context = array())
    {
        $this->log('INFO', $message, $context);
    }

    public function debug($message, array $context = array())
    {
        $this->log('DEBUG', $message, $context);
    }

    public function log($level, $message, array $context = array())
    {
        $handle = fopen($this->logPath, 'a');
        fwrite($handle, "$level: $message");
        fwrite($handle, print_r($context, true));
        fclose($handle);
    }
}
