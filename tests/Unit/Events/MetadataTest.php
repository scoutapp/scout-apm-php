<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events;

use Composer\InstalledVersions;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;
use Scoutapm\Events\Metadata;
use Scoutapm\Extension\ExtensionCapabilities;
use Scoutapm\Extension\Version;
use Scoutapm\Helper\FindApplicationRoot;
use Scoutapm\Helper\Timer;

use function gethostname;
use function json_decode;
use function json_encode;
use function putenv;
use function sprintf;
use function uniqid;

use const PHP_VERSION;

/**
 * @covers \Scoutapm\Events\Metadata
 * @psalm-import-type VersionList from Metadata
 */
final class MetadataTest extends TestCase
{
    private const FAKE_APPLICATION_ROOT = '/fake/path/to/app';

    /** @var ExtensionCapabilities&MockObject */
    private $phpExtension;
    /** @var FindApplicationRoot&MockObject */
    private $findApplicationRoot;
    /** @var DateTimeImmutable */
    private $time;

    public function setUp(): void
    {
        parent::setUp();

        $this->phpExtension        = $this->createMock(ExtensionCapabilities::class);
        $this->findApplicationRoot = $this->createMock(FindApplicationRoot::class);

        $this->findApplicationRoot
            ->method('__invoke')
            ->willReturn(self::FAKE_APPLICATION_ROOT);

        $this->time = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /**
     * @return string[][]
     *
     * @psalm-return VersionList
     */
    private function installedVersions(string $withScoutExtensionVersion): array
    {
        $composerPlatformVersions = [];

        foreach (InstalledVersions::getInstalledPackages() as $packageName) {
            $composerPlatformVersions[] = [
                $packageName === 'root' ? InstalledVersions::getRootPackage()['name'] : $packageName,
                sprintf(
                    '%s@%s',
                    (string) InstalledVersions::getPrettyVersion($packageName),
                    (string) InstalledVersions::getReference($packageName)
                ),
            ];
        }

        $composerPlatformVersions[] = ['ext-scoutapm', $withScoutExtensionVersion];

        /** @psalm-var VersionList $composerPlatformVersions */
        return $composerPlatformVersions;
    }

    /** @throws Exception */
    public function testMetadataFromConfigurationSerializesToJson(): void
    {
        $this->phpExtension->expects(self::once())
            ->method('version')
            ->willReturn(Version::fromString('1.2.3'));

        $config = Config::fromArray([
            ConfigKey::SCM_SUBDIRECTORY => '/fake/scm/subdirectory',
            ConfigKey::APPLICATION_NAME => 'My amazing application',
            ConfigKey::REVISION_SHA => 'abc123',
            ConfigKey::HOSTNAME => 'fake-hostname.scoutapm.com',
            ConfigKey::FRAMEWORK => 'Great Framework',
            ConfigKey::FRAMEWORK_VERSION => '1.2.3',
        ]);

        self::assertEquals(
            [
                'ApplicationEvent' => [
                    'timestamp' => $this->time->format(Timer::FORMAT_FOR_CORE_AGENT),
                    'event_value' => [
                        'language' => 'php',
                        'version' => PHP_VERSION,
                        'language_version' => PHP_VERSION,
                        'server_time' => $this->time->format(Timer::FORMAT_FOR_CORE_AGENT),
                        'framework' => 'Great Framework',
                        'framework_version' => '1.2.3',
                        'environment' => '',
                        'app_server' => '',
                        'hostname' => 'fake-hostname.scoutapm.com',
                        'database_engine' => '',
                        'database_adapter' => '',
                        'application_name' => 'My amazing application',
                        'libraries' => $this->installedVersions('1.2.3'),
                        'paas' => '',
                        'application_root' => self::FAKE_APPLICATION_ROOT,
                        'scm_subdirectory' => '/fake/scm/subdirectory',
                        'git_sha' => 'abc123',
                    ],
                    'event_type' => 'scout.metadata',
                    'source' => 'php',
                ],
            ],
            json_decode(json_encode(new Metadata($this->time, $config, $this->phpExtension, $this->findApplicationRoot)), true)
        );
    }

    /** @throws Exception */
    public function testAutoDetectedMetadataSerializesToJson(): void
    {
        $this->phpExtension->expects(self::once())
            ->method('version')
            ->willReturn(null);

        $config = Config::fromArray([]);

        self::assertEquals(
            [
                'ApplicationEvent' => [
                    'timestamp' => $this->time->format(Timer::FORMAT_FOR_CORE_AGENT),
                    'event_value' => [
                        'language' => 'php',
                        'version' => PHP_VERSION,
                        'language_version' => PHP_VERSION,
                        'server_time' => $this->time->format(Timer::FORMAT_FOR_CORE_AGENT),
                        'framework' => '',
                        'framework_version' => '',
                        'environment' => '',
                        'app_server' => '',
                        'hostname' => gethostname(),
                        'database_engine' => '',
                        'database_adapter' => '',
                        'application_name' => '',
                        'libraries' => $this->installedVersions('not installed'),
                        'paas' => '',
                        'application_root' => self::FAKE_APPLICATION_ROOT,
                        'scm_subdirectory' => '',
                        'git_sha' => InstalledVersions::getRootPackage()['reference'],
                    ],
                    'event_type' => 'scout.metadata',
                    'source' => 'php',
                ],
            ],
            json_decode(json_encode(new Metadata($this->time, $config, $this->phpExtension, $this->findApplicationRoot)), true)
        );
    }

    /** @throws Exception */
    public function testHerokuSlugCommitOverridesTheGitSha(): void
    {
        $testHerokuSlugCommit = uniqid('testHerokuSlugCommit', true);

        putenv('HEROKU_SLUG_COMMIT=' . $testHerokuSlugCommit);

        self::assertSame(
            $testHerokuSlugCommit,
            json_decode(json_encode(new Metadata(
                $this->time,
                Config::fromArray([]),
                $this->phpExtension,
                $this->findApplicationRoot
            )), true)['ApplicationEvent']['event_value']['git_sha']
        );

        putenv('HEROKU_SLUG_COMMIT');
    }
}
