<?php

declare(strict_types=1);

namespace Scoutapm\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Scoutapm\CoreAgent\Downloader;
use Scoutapm\CoreAgent\Launcher;
use Scoutapm\CoreAgent\Verifier;

use function sprintf;

/** @internal */
final class CoreAgent extends Command
{
    /** @var string */
    protected $signature = 'scoutapm:core-agent {--download : Supply this flag if the Core Agent should be downloaded} {--launch : Supply this flag if the Core Agent should be launched}';
    /** @var string|null */
    protected $description = 'Manage the ScoutAPM core agent';

    public function handle(Verifier $verifier, Downloader $downloader, Launcher $launcher): int
    {
        $shouldDownload = $this->option('download');
        $shouldLaunch   = $this->option('launch');

        if (! $shouldDownload && ! $shouldLaunch) {
            $this->warn('You must specify --download and/or --launch flags');

            return 1;
        }

        if ($shouldDownload) {
            if (! $this->downloadIfNeeded($verifier, $downloader)) {
                return 1;
            }
        }

        if (! $shouldLaunch) {
            return 0;
        }

        if (! $this->launchIfExists($verifier, $launcher)) {
            return 1;
        }

        return 0;
    }

    private function downloadIfNeeded(Verifier $verifier, Downloader $downloader): bool
    {
        $this->info('Checking if core agent already exists...');
        $coreAgentBinPath = $verifier->verify();

        if ($coreAgentBinPath !== null) {
            $this->warn(sprintf('Core agent already exists at: %s', $coreAgentBinPath));

            return true;
        }

        $this->info('Core agent does not exist, downloading...');

        $downloader->download();

        $coreAgentBinPath = $verifier->verify();

        if ($coreAgentBinPath === null) {
            $this->error('Failed to download Core Agent - check the logs');

            return false;
        }

        $this->info(sprintf('Download complete to: %s', $coreAgentBinPath));

        return true;
    }

    private function launchIfExists(Verifier $verifier, Launcher $launcher): bool
    {
        $coreAgentBinPath = $verifier->verify();
        if ($coreAgentBinPath === null) {
            $this->error('Could not verify that Core Agent exists');

            return false;
        }

        $launcher->launch($coreAgentBinPath);

        $this->info(sprintf('Launch of %s completed.', $coreAgentBinPath));

        return true;
    }
}
