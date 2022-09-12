<?php
/** @noinspection AutoloadingIssuesInspection */
/** @noinspection PhpIllegalPsrClassPathInspection */
declare(strict_types=1);

namespace {
    function scoutapm_enable_instrumentation(bool $enabled): void
    {
    }

    /** @psalm-return list<array{function:string, entered:float, exited: float, time_taken: float, argv: mixed[]}> */
    function scoutapm_get_calls(): array
    {
    }

    /** @return list<string> */
    function scoutapm_list_instrumented_functions(): array
    {
    }
}

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

namespace Illuminate\Console\Events {
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;

    class CommandStarting
    {
        /** @var string|null */
        public $command;
        /** @param string|null $command */
        public function __construct($command, InputInterface $input, OutputInterface $output) {}
    }
    class CommandFinished
    {
        /** @var string|null */
        public $command;
        /**
         * @param string|null $command
         * @param int $exitCode
         */
        public function __construct($command, InputInterface $input, OutputInterface $output, $exitCode) {}
    }
}
