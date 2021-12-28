<?php

declare(strict_types=1);

namespace Scoutapm\Helper\FindApplicationRoot;

use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;
use Scoutapm\Helper\LocateFileOrFolder\LocateFileOrFolder;
use Scoutapm\Helper\Superglobals\Superglobals;

use function array_key_exists;
use function is_string;

/** @internal This is not covered by BC promise */
final class FindApplicationRootWithConfigOverride implements FindApplicationRoot
{
    /** @var LocateFileOrFolder */
    private $locateFileOrFolder;
    /** @var Config */
    private $config;
    /** @var Superglobals */
    private $superglobals;
    /** @var ?string */
    private $memoizedApplicationRoot;

    public function __construct(LocateFileOrFolder $locateFileOrFolder, Config $config, Superglobals $superglobals)
    {
        $this->locateFileOrFolder = $locateFileOrFolder;
        $this->config             = $config;
        $this->superglobals       = $superglobals;
    }

    public function __invoke(): string
    {
        if (is_string($this->memoizedApplicationRoot)) {
            return $this->memoizedApplicationRoot;
        }

        /** @var mixed $applicationRootConfiguration */
        $applicationRootConfiguration = $this->config->get(ConfigKey::APPLICATION_ROOT);
        if (is_string($applicationRootConfiguration) && $applicationRootConfiguration !== '') {
            $this->memoizedApplicationRoot = $applicationRootConfiguration;

            return $applicationRootConfiguration;
        }

        $composerJsonLocation = $this->locateFileOrFolder->__invoke('composer.json');
        if ($composerJsonLocation !== null) {
            $this->memoizedApplicationRoot = $composerJsonLocation;

            return $composerJsonLocation;
        }

        $server = $this->superglobals->server();
        if (! array_key_exists('DOCUMENT_ROOT', $server)) {
            $this->memoizedApplicationRoot = '';

            return '';
        }

        $this->memoizedApplicationRoot = $server['DOCUMENT_ROOT'];

        return $server['DOCUMENT_ROOT'];
    }
}
