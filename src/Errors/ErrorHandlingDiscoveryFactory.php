<?php

declare(strict_types=1);

namespace Scoutapm\Errors;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Log\LoggerInterface;
use Scoutapm\Config;
use Scoutapm\Errors\ScoutClient\CompressPayload;
use Scoutapm\Errors\ScoutClient\HttpErrorReportingClient;
use Scoutapm\Helper\DetermineHostname\DetermineHostnameWithConfigOverride;
use Scoutapm\Helper\FindApplicationRoot\FindApplicationRootWithConfigOverride;
use Scoutapm\Helper\LocateFileOrFolder\LocateFileOrFolderUsingFilesystem;
use Scoutapm\Helper\RootPackageGitSha\FindRootPackageGitShaWithHerokuAndConfigOverride;
use Scoutapm\Helper\Superglobals\Superglobals;
use Throwable;

use function sprintf;

final class ErrorHandlingDiscoveryFactory
{
    public static function create(Config $config, LoggerInterface $logger, Superglobals $superglobals): ErrorHandling
    {
        if (! (bool) $config->get(Config\ConfigKey::ERRORS_ENABLED)) {
            return new NoErrorHandling();
        }

        try {
            return new ScoutErrorHandling(
                new HttpErrorReportingClient(
                    Psr18ClientDiscovery::find(),
                    Psr17FactoryDiscovery::findRequestFactory(),
                    Psr17FactoryDiscovery::findStreamFactory(),
                    new CompressPayload(),
                    $config,
                    $logger,
                    new FindApplicationRootWithConfigOverride(new LocateFileOrFolderUsingFilesystem(), $config, $superglobals),
                    $superglobals,
                    new DetermineHostnameWithConfigOverride($config),
                    new FindRootPackageGitShaWithHerokuAndConfigOverride($config)
                ),
                $config,
                $logger
            );
        } catch (Throwable $noHttpClient) {
            /**
             * Note, ideally we catch a {@see \Http\Discovery\Exception\NotFoundException} here, but Symfony is a
             * special case and causes an {@see \E_USER_WARNING} to be raised, so we also need to catch
             * {@see \ErrorException} here. Since union catches aren't available until PHP 8, catch {@see \Throwable}
             * for simplicity for now.
             *
             * @link https://github.com/php-http/discovery/issues/201
             */
            $logger->warning(
                sprintf(
                    <<<ERROR
Scout Error handling was enabled, but we could not find a PSR-18 HTTP client and/or PSR-17 HTTP message factory to use.

PHP-HTTP PSR-18 HTTP discovery exception: %s

TL;DR:

 * If you are using Symfony, run `composer require php-http/httplug nyholm/psr7`
 * If you are on PHP 7.1, run `composer require php-http/guzzle6-adapter nyholm/psr7`
 * If you are on PHP 7.2+, run `composer require guzzlehttp/guzzle:^7.0`

If you want to use a different library than the above suggestions, other options are available. You may visit the
following pages to find packages that provide the implementations required:

 * https://packagist.org/providers/psr/http-client-implementation
 * https://packagist.org/providers/psr/http-factory-implementation
 * https://packagist.org/providers/psr/http-message-implementation
ERROR
                    ,
                    $noHttpClient->getMessage()
                ),
                ['exception' => $noHttpClient]
            );

            return new NoErrorHandling();
        }
    }
}
