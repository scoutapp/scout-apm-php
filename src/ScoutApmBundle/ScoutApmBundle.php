<?php

declare(strict_types=1);

namespace Scoutapm\ScoutApmBundle;

use Doctrine\DBAL\Connection;
use Scoutapm\ScoutApmBundle\EventListener\DoctrineSqlLogger;
use Symfony\Component\HttpKernel\Bundle\Bundle;

use function array_map;
use function assert;

final class ScoutApmBundle extends Bundle
{
    private const DOCTRINE_CONNECTIONS = ['doctrine.dbal.default_connection'];

    public function boot(): void
    {
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
}
