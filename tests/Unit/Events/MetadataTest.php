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
use Scoutapm\Extension\ExtentionCapabilities;
use Scoutapm\Extension\Version;
use Scoutapm\Helper\LocateFileOrFolder;
use Scoutapm\Helper\Timer;
use const PHP_VERSION;
use function gethostname;
use function json_decode;
use function json_encode;
use function putenv;
use function sprintf;
use function uniqid;

/**
 * @covers \Scoutapm\Events\Metadata
 * @psalm-import-type VersionList from Metadata
 */
final class MetadataTest extends TestCase
{
    /** @var ExtentionCapabilities&MockObject */
    private $phpExtension;
    /** @var LocateFileOrFolder&MockObject */
    private $locateFileOrFolder;
    /** @var DateTimeImmutable */
    private $time;

    public function setUp() : void
    {
        parent::setUp();

        $this->phpExtension       = $this->createMock(ExtentionCapabilities::class);
        $this->locateFileOrFolder = $this->createMock(LocateFileOrFolder::class);

        $this->time = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /**
     * @return string[][]
     *
     * @psalm-return VersionList
     */
    private function installedVersions(string $withScoutExtensionVersion) : array
    {
        $composerPlatformVersions = [];

        foreach (InstalledVersions::getInstalledPackages() as $packageName) {
            $composerPlatformVersions[] = [
                $packageName === 'root' ? InstalledVersions::getRootPackage()['name'] : $packageName,
                sprintf(
                    '%s@%s',
                    InstalledVersions::getPrettyVersion($packageName),
                    InstalledVersions::getReference($packageName)
                ),
            ];
        }

        $composerPlatformVersions[] = ['ext-scoutapm', $withScoutExtensionVersion];

        /** @psalm-var VersionList $composerPlatformVersions */
        return $composerPlatformVersions;
    }

    /** @throws Exception */
    public function testMetadataFromConfigurationSerializesToJson() : void
    {
        $this->phpExtension->expects(self::once())
            ->method('version')
            ->willReturn(Version::fromString('1.2.3'));

        $config = Config::fromArray([
            ConfigKey::APPLICATION_ROOT => '/fake/app/root',
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
                        'application_root' => '/fake/app/root',
                        'scm_subdirectory' => '/fake/scm/subdirectory',
                        'git_sha' => 'abc123',
                    ],
                    'event_type' => 'scout.metadata',
                    'source' => 'php',
                ],
            ],
            json_decode(json_encode(new Metadata($this->time, $config, $this->phpExtension, $this->locateFileOrFolder)), true)
        );
    }

    /** @throws Exception */
    public function testAutoDetectedMetadataSerializesToJson() : void
    {
        $this->phpExtension->expects(self::once())
            ->method('version')
            ->willReturn(null);

        $config = Config::fromArray([]);

        $_SERVER['DOCUMENT_ROOT'] = '/fake/document/root';

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
                        'application_root' => '/fake/document/root',
                        'scm_subdirectory' => '',
                        'git_sha' => InstalledVersions::getRootPackage()['reference'],
                    ],
                    'event_type' => 'scout.metadata',
                    'source' => 'php',
                ],
            ],
            json_decode(json_encode(new Metadata($this->time, $config, $this->phpExtension, $this->locateFileOrFolder)), true)
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
                $this->time,
                Config::fromArray([]),
                $this->phpExtension,
                $this->locateFileOrFolder
            )), true)['ApplicationEvent']['event_value']['git_sha']
        );

        putenv('HEROKU_SLUG_COMMIT');
    }
}
