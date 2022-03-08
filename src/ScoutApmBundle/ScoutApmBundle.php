<?php

declare(strict_types=1);

namespace Scoutapm\ScoutApmBundle;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Scoutapm\Helper\ComposerPackagesCheck;
use Scoutapm\ScoutApmBundle\EventListener\DoctrineSqlLogger;
use Symfony\Component\HttpKernel\Bundle\Bundle;

use function array_map;
use function assert;

/** @internal This class extends a third party vendor, so we mark as internal to not expose upstream BC breaks */
final class ScoutApmBundle extends Bundle
{
    private const DOCTRINE_CONNECTIONS = ['doctrine.dbal.default_connection'];

    public function boot(): void
    {
        $this->safelyCheckForSymfonyPackagePresence();

        /** @noinspection UnusedFunctionResultInspection */
        array_map(
            function (string $connectionServiceName): void {
                if (! $this->container->has($connectionServiceName)) {
                    return;
                }

                $sqlLogger = $this->container->get(DoctrineSqlLogger::class);
                assert($sqlLogger instanceof DoctrineSqlLogger);
                $connection = $this->container->get($connectionServiceName);
                assert($connection instanceof Connection);

                $sqlLogger->registerWith($connection);
            },
            self::DOCTRINE_CONNECTIONS
        );
    }

    private function safelyCheckForSymfonyPackagePresence(): void
    {
        if (! $this->container->has(LoggerInterface::class)) {
            return;
        }

        $logger = $this->container->get(LoggerInterface::class);
        if (! $logger instanceof LoggerInterface) {
            return;
        }

        ComposerPackagesCheck::logIfSymfonyPackageNotPresent($logger);
    }
}
