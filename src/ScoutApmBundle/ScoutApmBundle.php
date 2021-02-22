<?php

declare(strict_types=1);

namespace Scoutapm\ScoutApmBundle;

use Doctrine\DBAL\Connection;
use Scoutapm\ScoutApmBundle\EventListener\DoctrineSqlLogger;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use function array_map;

final class ScoutApmBundle extends Bundle
{
    private const DOCTRINE_CONNECTIONS = ['doctrine.dbal.default_connection'];

    public function boot() : void
    {
        /** @noinspection UnusedFunctionResultInspection */
        array_map(
            function (string $connectionServiceName) : void {
                if (! $this->container->has($connectionServiceName)) {
                    return;
                }

                /** @var DoctrineSqlLogger $sqlLogger */
                $sqlLogger = $this->container->get(DoctrineSqlLogger::class);
                /** @var Connection $connection */
                $connection = $this->container->get($connectionServiceName);

                $sqlLogger->registerWith($connection);
            },
            self::DOCTRINE_CONNECTIONS
        );
    }
}
