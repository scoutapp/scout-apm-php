<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Helper;

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;
use Scoutapm\Config;
use Scoutapm\Helper\RootPackageGitSha\FindRootPackageGitShaWithHerokuAndConfigOverride;
use Scoutapm\Helper\Superglobals\SuperglobalsArrays;

/** @covers \Scoutapm\Helper\RootPackageGitSha\FindRootPackageGitShaWithHerokuAndConfigOverride */
final class FindRootPackageGitShaWithHerokuAndConfigOverrideTest extends TestCase
{
    public function testFindingRootPackageGitShaFromConfigOverride(): void
    {
        self::assertSame(
            'abcdef',
            (new FindRootPackageGitShaWithHerokuAndConfigOverride(
                Config::fromArray([Config\ConfigKey::REVISION_SHA => 'abcdef']),
                new SuperglobalsArrays([], [], [], [], [])
            ))()
        );
    }

    public function testFindingRootPackageGitShaFromHerokuSlugCommit(): void
    {
        self::assertSame(
            'bcdef1',
            (new FindRootPackageGitShaWithHerokuAndConfigOverride(
                Config::fromArray([]),
                new SuperglobalsArrays([], [], ['HEROKU_SLUG_COMMIT' => 'bcdef1'], [], [])
            ))()
        );
    }

    public function testFindingRootPackageGitShaFallbackUsingComposer(): void
    {
        self::assertSame(
            InstalledVersions::getRootPackage()['reference'],
            (new FindRootPackageGitShaWithHerokuAndConfigOverride(
                Config::fromArray([]),
                new SuperglobalsArrays([], [], [], [], [])
            ))()
        );
    }
}
