<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Helper;

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;
use Scoutapm\Config;
use Scoutapm\Helper\RootPackageGitSha\FindRootPackageGitShaWithHerokuAndConfigOverride;

use function putenv;

/** @covers \Scoutapm\Helper\RootPackageGitSha\FindRootPackageGitShaWithHerokuAndConfigOverride */
final class FindRootPackageGitShaWithHerokuAndConfigOverrideTest extends TestCase
{
    public function testFindingRootPackageGitShaFromConfigOverride(): void
    {
        self::assertSame(
            'abcdef',
            (new FindRootPackageGitShaWithHerokuAndConfigOverride(Config::fromArray([Config\ConfigKey::REVISION_SHA => 'abcdef'])))()
        );
    }

    public function testFindingRootPackageGitShaFromHerokuSlugCommit(): void
    {
        putenv('HEROKU_SLUG_COMMIT=bcdef1');
        self::assertSame(
            'bcdef1',
            (new FindRootPackageGitShaWithHerokuAndConfigOverride(Config::fromArray([])))()
        );
        putenv('HEROKU_SLUG_COMMIT');
    }

    public function testFindingRootPackageGitShaFallbackUsingComposer(): void
    {
        self::assertSame(
            InstalledVersions::getRootPackage()['reference'],
            (new FindRootPackageGitShaWithHerokuAndConfigOverride(Config::fromArray([])))()
        );
    }
}
