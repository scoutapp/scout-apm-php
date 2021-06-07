<?php
/** @noinspection AutoloadingIssuesInspection */
/** @noinspection PhpIllegalPsrClassPathInspection */
declare(strict_types=1);

namespace Composer {
    class InstalledVersions {
        /**
         * @psalm-return list<string>
         */
        public static function getInstalledPackages()
        {
        }

        /**
         * @psalm-return array{
         *      name: string,
         *      version: string,
         *      reference: string,
         *      pretty_version: string,
         *      aliases: string[],
         *      dev: bool,
         *      install_path: string
         * }
         */
        public static function getRootPackage()
        {
        }

        /**
         * @psalm-param string $packageName
         * @psalm-return string|null
         */
        public static function getReference($packageName)
        {
        }

        /**
         * @psalm-param string $packageName
         * @psalm-return string|null
         */
        public static function getPrettyVersion($packageName)
        {
        }

        /**
         * @psalm-param string $packageName
         * @psalm-param bool $includeDevRequirements
         * @psalm-return bool
         */
        public static function isInstalled($packageName, $includeDevRequirements = true)
        {
        }
    }
}
