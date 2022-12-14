<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests;

use BadMethodCallException;
use Psr\Log\AbstractLogger;

use function call_user_func_array;
use function is_string;
use function method_exists;
use function preg_match;
use function strpos;
use function strtolower;

/**
 * Used for testing purposes.
 *
 * It records all records and gives you access to them for verification.
 *
 * @method bool hasEmergency($record)
 * @method bool hasAlert($record)
 * @method bool hasCritical($record)
 * @method bool hasError($record)
 * @method bool hasWarning($record)
 * @method bool hasNotice($record)
 * @method bool hasInfo($record)
 * @method bool hasDebug($record)
 * @method bool hasEmergencyRecords()
 * @method bool hasAlertRecords()
 * @method bool hasCriticalRecords()
 * @method bool hasErrorRecords()
 * @method bool hasWarningRecords()
 * @method bool hasNoticeRecords()
 * @method bool hasInfoRecords()
 * @method bool hasDebugRecords()
 * @method bool hasEmergencyThatContains($message)
 * @method bool hasAlertThatContains($message)
 * @method bool hasCriticalThatContains($message)
 * @method bool hasErrorThatContains($message)
 * @method bool hasWarningThatContains($message)
 * @method bool hasNoticeThatContains($message)
 * @method bool hasInfoThatContains($message)
 * @method bool hasDebugThatContains($message)
 * @method bool hasEmergencyThatMatches($message)
 * @method bool hasAlertThatMatches($message)
 * @method bool hasCriticalThatMatches($message)
 * @method bool hasErrorThatMatches($message)
 * @method bool hasWarningThatMatches($message)
 * @method bool hasNoticeThatMatches($message)
 * @method bool hasInfoThatMatches($message)
 * @method bool hasDebugThatMatches($message)
 * @method bool hasEmergencyThatPasses($message)
 * @method bool hasAlertThatPasses($message)
 * @method bool hasCriticalThatPasses($message)
 * @method bool hasErrorThatPasses($message)
 * @method bool hasWarningThatPasses($message)
 * @method bool hasNoticeThatPasses($message)
 * @method bool hasInfoThatPasses($message)
 * @method bool hasDebugThatPasses($message)
 * @psalm-type LogRecord = array{level: string, message: string, context: array}
 */
class TestLogger extends AbstractLogger
{
    /** @psalm-var list<LogRecord> */
    public $records = [];

    /** @psalm-var array<string,list<LogRecord>> */
    public $recordsByLevel = [];

    /** @inheritdoc */
    public function log($level, $message, array $context = []): void
    {
        /** @psalm-suppress RedundantCastGivenDocblockType */
        $record = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];

        $this->recordsByLevel[$record['level']][] = $record;
        $this->records[]                          = $record;
    }

    public function hasRecords(string $level): bool
    {
        return isset($this->recordsByLevel[$level]);
    }

    /** @param string|array{message:string, context?: mixed} $record */
    public function hasRecord($record, string $level): bool
    {
        if (is_string($record)) {
            $record = ['message' => $record];
        }

        return $this->hasRecordThatPasses(
            /** @psalm-param LogRecord $rec */
            static function ($rec) use ($record): bool {
                if ($rec['message'] !== $record['message']) {
                    return false;
                }

                return ! isset($record['context']) || $rec['context'] === $record['context'];
            },
            $level
        );
    }

    public function hasRecordThatContains(string $message, string $level): bool
    {
        return $this->hasRecordThatPasses(
            /** @psalm-param LogRecord $rec */
            static function ($rec) use ($message): bool {
                return strpos($rec['message'], $message) !== false;
            },
            $level
        );
    }

    public function hasRecordThatMatches(string $regex, string $level): bool
    {
        return $this->hasRecordThatPasses(
            /** @psalm-param LogRecord $rec */
            static function ($rec) use ($regex): bool {
                return preg_match($regex, $rec['message']) > 0;
            },
            $level
        );
    }

    /** @psalm-param callable(LogRecord):bool $predicate */
    public function hasRecordThatPasses(callable $predicate, string $level): bool
    {
        if (! isset($this->recordsByLevel[$level])) {
            return false;
        }

        foreach ($this->recordsByLevel[$level] as $rec) {
            if ($predicate($rec)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<array-key, mixed> $args */
    public function __call(string $method, array $args): bool
    {
        if (preg_match('/(.*)(Debug|Info|Notice|Warning|Error|Critical|Alert|Emergency)(.*)/', $method, $matches) > 0) {
            $genericMethod = $matches[1] . ($matches[3] !== 'Records' ? 'Record' : '') . $matches[3];
            $level         = strtolower($matches[2]);
            if (method_exists($this, $genericMethod)) {
                $args[] = $level;

                return (bool) call_user_func_array([$this, $genericMethod], $args);
            }
        }

        throw new BadMethodCallException('Call to undefined method ' . static::class . '::' . $method . '()');
    }

    public function reset(): void
    {
        $this->records        = [];
        $this->recordsByLevel = [];
    }
}
