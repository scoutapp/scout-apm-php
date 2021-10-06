<?php

declare(strict_types=1);

namespace Scoutapm\Helper\RootPackageGitSha;

use Composer\InstalledVersions;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;

use function array_key_exists;
use function class_exists;
use function getenv;
use function is_array;
use function is_string;
use function method_exists;

/** @internal */
final class FindRootPackageGitShaWithHerokuAndConfigOverride implements FindRootPackageGitSha
{
    /** @var Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function __invoke(): string
    {
        /** @var mixed $revisionShaConfiguration */
        $revisionShaConfiguration = $this->config->get(ConfigKey::REVISION_SHA);
        if (is_string($revisionShaConfiguration) && $revisionShaConfiguration !== '') {
            return $revisionShaConfiguration;
        }

        $herokuSlugCommit = getenv('HEROKU_SLUG_COMMIT');
        if (is_string($herokuSlugCommit) && $herokuSlugCommit !== '') {
            return $herokuSlugCommit;
        }

        if (class_exists(InstalledVersions::class) && method_exists(InstalledVersions::class, 'getRootPackage')) {
            /** @var mixed $rootPackage */
            $rootPackage = InstalledVersions::getRootPackage();
            if (is_array($rootPackage) && array_key_exists('reference', $rootPackage) && is_string($rootPackage['reference'])) {
                return $rootPackage['reference'];
            }
        }

        return '';
    }
}
