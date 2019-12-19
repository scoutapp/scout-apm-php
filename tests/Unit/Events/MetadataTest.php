<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use PackageVersions\Versions;
use PHPUnit\Framework\TestCase;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;
use Scoutapm\Events\Metadata;
use Scoutapm\Helper\Timer;
use const PHP_VERSION;
use function array_keys;
use function array_map;
use function explode;
use function gethostname;
use function json_decode;
use function json_encode;
use function putenv;
use function uniqid;

/** @covers \Scoutapm\Events\Metadata */
final class MetadataTest extends TestCase
{
    /** @throws Exception */
    public function testMetadataFromConfigurationSerializesToJson() : void
    {
        $config = Config::fromArray([
            ConfigKey::APPLICATION_ROOT => '/fake/app/root',
            ConfigKey::SCM_SUBDIRECTORY => '/fake/scm/subdirectory',
            ConfigKey::APPLICATION_NAME => 'My amazing application',
            ConfigKey::REVISION_SHA => 'abc123',
            ConfigKey::HOSTNAME => 'fake-hostname.scoutapm.com',
            ConfigKey::FRAMEWORK => 'Great Framework',
            ConfigKey::FRAMEWORK_VERSION => '1.2.3',
        ]);

        $time = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        self::assertEquals(
            [
                'ApplicationEvent' => [
                    'timestamp' => $time->format(Timer::FORMAT_FOR_CORE_AGENT),
                    'event_value' => [
                        'language' => 'php',
                        'version' => PHP_VERSION,
                        'language_version' => PHP_VERSION,
                        'server_time' => $time->format(Timer::FORMAT_FOR_CORE_AGENT),
                        'framework' => 'Great Framework',
                        'framework_version' => '1.2.3',
                        'environment' => '',
                        'app_server' => '',
                        'hostname' => 'fake-hostname.scoutapm.com',
                        'database_engine' => '',
                        'database_adapter' => '',
                        'application_name' => 'My amazing application',
                        'libraries' => array_map(
                            static function ($package, $version) {
                                return [$package, $version];
                            },
                            array_keys(Versions::VERSIONS),
                            Versions::VERSIONS
                        ),
                        'paas' => '',
                        'application_root' => '/fake/app/root',
                        'scm_subdirectory' => '/fake/scm/subdirectory',
                        'git_sha' => 'abc123',
                    ],
                    'event_type' => 'scout.metadata',
                    'source' => 'php',
                ],
            ],
            json_decode(json_encode(new Metadata($time, $config)), true)
        );
    }

    /** @throws Exception */
    public function testAutoDetectedMetadataSerializesToJson() : void
    {
        $config = Config::fromArray([]);

        $time = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $_SERVER['DOCUMENT_ROOT'] = '/fake/document/root';

        self::assertEquals(
            [
                'ApplicationEvent' => [
                    'timestamp' => $time->format(Timer::FORMAT_FOR_CORE_AGENT),
                    'event_value' => [
                        'language' => 'php',
                        'version' => PHP_VERSION,
                        'language_version' => PHP_VERSION,
                        'server_time' => $time->format(Timer::FORMAT_FOR_CORE_AGENT),
                        'framework' => '',
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
                        'application_root' => '/fake/document/root',
                        'scm_subdirectory' => '',
                        'git_sha' => explode('@', Versions::getVersion(Versions::ROOT_PACKAGE_NAME))[1],
                    ],
                    'event_type' => 'scout.metadata',
                    'source' => 'php',
                ],
            ],
            json_decode(json_encode(new Metadata($time, $config)), true)
        );
    }

    /** @throws Exception */
    public function testHerokuSlugCommitOverridesTheGitSha() : void
    {
        $testHerokuSlugCommit = uniqid('testHerokuSlugCommit', true);

        putenv('HEROKU_SLUG_COMMIT=' . $testHerokuSlugCommit);

        self::assertSame(
            $testHerokuSlugCommit,
            json_decode(json_encode(new Metadata(
                new DateTimeImmutable('now', new DateTimeZone('UTC')),
                Config::fromArray([])
            )), true)['ApplicationEvent']['event_value']['git_sha']
        );

        putenv('HEROKU_SLUG_COMMIT');
    }
}
