<?php

declare(strict_types=1);

namespace Scoutapm\Helper;

use Composer\InstalledVersions;
use Psr\Log\LoggerInterface;

use function class_exists;
use function sprintf;

final class ComposerPackagesCheck
{
    private const PHP_PACKAGE_NAME     = 'scoutapp/scout-apm-php';
    private const LARAVEL_PACKAGE_NAME = 'scoutapp/scout-apm-laravel';
    private const SYMFONY_PACKAGE_NAME = 'scoutapp/scout-apm-symfony-bundle';

    public static function logIfLaravelPackageNotPresent(LoggerInterface $log): void
    {
        self::logIfPackageNotPresent($log, 'Laravel', self::LARAVEL_PACKAGE_NAME);
    }

    public static function logIfSymfonyPackageNotPresent(LoggerInterface $log): void
    {
        self::logIfPackageNotPresent($log, 'Symfony', self::SYMFONY_PACKAGE_NAME);
    }

    public static function phpLibraryVersion(): string
    {
        if (
            ! class_exists(InstalledVersions::class)
            || ! self::packageIsInstalled(self::PHP_PACKAGE_NAME)
        ) {
            return 'none';
        }

        $version = (string) InstalledVersions::getPrettyVersion(self::PHP_PACKAGE_NAME);

        if ($version === '') {
            return 'unknown';
        }

        return $version;
    }

    private static function logIfPackageNotPresent(
        LoggerInterface $log,
        string $frameworkDetected,
        string $requiredPackage
    ): void {
        if (self::packageIsInstalled($requiredPackage)) {
            return;
        }

        $log->info(sprintf(
            'We detected you are running %s, but did not have %s installed.',
            $frameworkDetected,
            $requiredPackage
        ));
    }

    private static function packageIsInstalled(string $package): bool
    {
        // Can't detect anything without Composer v2 API :(
        if (! class_exists(InstalledVersions::class)) {
            return true;
        }

        return InstalledVersions::isInstalled($package);
    }
}
