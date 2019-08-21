<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use PackageVersions\Versions;
use PHPUnit\Framework\TestCase;
use Scoutapm\Events\Metadata;
use Scoutapm\Helper\Timer;
use const PHP_VERSION;
use function array_keys;
use function array_map;
use function explode;
use function gethostname;
use function json_decode;
use function json_encode;

/** @covers \Scoutapm\Events\Metadata */
final class MetadataTest extends TestCase
{
    /** @throws Exception */
    public function testMetadataSerializesToJson() : void
    {
        $time = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $serialized = json_encode(new Metadata($time));

        self::assertEquals(
            [
                'ApplicationEvent' => [
                    'timestamp' => $time->format(Timer::FORMAT_FOR_CORE_AGENT),
                    'event_value' => [
                        'language' => 'php',
                        'version' => PHP_VERSION,
                        'server_time' => $time->format(Timer::FORMAT_FOR_CORE_AGENT),
                        'framework' => 'laravel',
                        'framework_version' => '',
                        'environment' => '',
                        'app_server' => '',
                        'hostname' => gethostname(),
                        'database_engine' => '',
                        'database_adapter' => '',
                        'application_name' => '',
                        'libraries' => array_map(
                            static function ($package, $version) {
                                return [$package, $version];
                            },
                            array_keys(Versions::VERSIONS),
                            Versions::VERSIONS
                        ),
                        'paas' => '',
                        'application_root' => '',
                        'scm_subdirectory' => '',
                        'git_sha' => explode('@', Versions::getVersion(Versions::ROOT_PACKAGE_NAME))[1],
                    ],
                    'event_type' => 'scout.metadata',
                    'source' => 'php',
                ],
            ],
            json_decode($serialized, true)
        );
    }
}
