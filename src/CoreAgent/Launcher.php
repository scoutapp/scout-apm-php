<?php

declare(strict_types=1);

namespace Scoutapm\CoreAgent;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Scoutapm\Connector\ConnectionAddress;
use Throwable;

use function array_map;
use function exec;
use function explode;
use function function_exists;
use function implode;
use function in_array;
use function ini_get;
use function sprintf;
use function stripos;

/** @internal */
class Launcher
{
    /** @var LoggerInterface */
    private $logger;
    /** @var ConnectionAddress */
    private $connectionAddress;
    /** @var string|null */
    private $coreAgentLogLevel;
    /** @var string */
    private $coreAgentLogFile;
    /** @var string|null */
    private $coreAgentConfigFile;

    public function __construct(
        LoggerInterface $logger,
        ConnectionAddress $connectionAddress,
        ?string $coreAgentLogLevel,
        ?string $coreAgentLogFile,
        ?string $coreAgentConfigFile
    ) {
        $this->logger              = $logger;
        $this->connectionAddress   = $connectionAddress;
        $this->coreAgentLogLevel   = $coreAgentLogLevel;
        $this->coreAgentConfigFile = $coreAgentConfigFile;
        $this->coreAgentLogFile    = $coreAgentLogFile ?? '/dev/null';
    }

    public function launch(string $coreAgentBinaryPath): bool
    {
        if (! $this->phpCanExec()) {
            return false;
        }

        $this->logger->debug('Core Agent Launch in Progress');
        try {
            $commandParts = [
                $coreAgentBinaryPath,
                'start',
                '--daemonize',
                'true',
                '--log-file',
                $this->coreAgentLogFile,
            ];

            if ($this->coreAgentLogLevel !== null) {
                $commandParts[] = '--log-level';
                $commandParts[] = $this->coreAgentLogLevel;
            }

            if ($this->coreAgentConfigFile !== null) {
                $commandParts[] = '--config-file';
                $commandParts[] = $this->coreAgentConfigFile;
            }

            if ($this->connectionAddress->isTcpAddress()) {
                $commandParts[] = '--tcp';
                $commandParts[] = $this->connectionAddress->tcpBindAddressPort();
            }

            if ($this->connectionAddress->isSocketPath()) {
                $commandParts[] = '--socket';
                $commandParts[] = $this->connectionAddress->socketPath();
            }

            $escapedCommand = implode(' ', array_map('escapeshellarg', $commandParts));

            $this->logger->debug(sprintf('Launching core agent with command: %s', $escapedCommand));

            exec($escapedCommand . ' 2>&1', $output, $exitStatus);

            /** @noinspection UnnecessaryClosureInspection */
            $this->assertOutputDoesNotContainErrors(
                implode(
                    "\n",
                    array_map(
                        static function ($item): string {
                            return (string) $item;
                        },
                        $output
                    )
                ),
                $exitStatus
            );

            return true;
        } catch (Throwable $e) {
            $this->logger->debug(
                sprintf('Failed to launch core agent - exception %s', $e->getMessage()),
                ['exception' => $e]
            );

            return false;
        }
    }

    private function phpCanExec(): bool
    {
        if (! function_exists('exec')) {
            $this->logger->warning('PHP function exec is not available');

            return false;
        }

        if (in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
            $this->logger->warning('PHP function exec is in disabled_functions');

            return false;
        }

        if (exec('echo scoutapm') !== 'scoutapm') {
            $this->logger->warning('PHP function exec did not return expected value');

            return false;
        }

        $this->logger->debug('exec is available');

        return true;
    }

    private function assertOutputDoesNotContainErrors(string $output, int $exitStatus): void
    {
        if (stripos($output, "version `GLIBC_2.18' not found") !== false) {
            throw new RuntimeException('core-agent currently needs at least glibc 2.18. Output: ' . $output);
        }

        if ($exitStatus !== 0) {
            throw new RuntimeException('core-agent exited with non-zero status. Output: ' . $output);
        }
    }
}
