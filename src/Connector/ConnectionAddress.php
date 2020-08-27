<?php

declare(strict_types=1);

namespace Scoutapm\Connector;

use LogicException;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;
use function explode;
use function strlen;
use function strpos;
use function substr;

/** @internal */
final class ConnectionAddress
{
    private const TCP_ADDRESS_MARKER = 'tcp://';

    /** @var string */
    private $path;

    private function __construct(string $path)
    {
        $this->path = $path;
    }

    public static function fromConfig(Config $config) : self
    {
        return new self($config->get(ConfigKey::CORE_AGENT_SOCKET_PATH));
    }

    public function isTcpAddress() : bool
    {
        /** @noinspection StrStartsWithCanBeUsedInspection */
        return strpos($this->path, self::TCP_ADDRESS_MARKER) === 0;
    }

    public function isSocketPath() : bool
    {
        return ! $this->isTcpAddress();
    }

    public function socketPath() : string
    {
        if (! $this->isSocketPath()) {
            throw new LogicException('Cannot extract socket path from a non-socket address');
        }

        return $this->path;
    }

    public function tcpBindAddressPort() : string
    {
        if (! $this->isTcpAddress()) {
            throw new LogicException('Cannot extract TCP address from a non-TCP address');
        }

        return substr($this->path, strlen(self::TCP_ADDRESS_MARKER));
    }

    public function tcpBindAddress() : string
    {
        return explode(':', $this->tcpBindAddressPort(), 2)[0];
    }

    public function tcpBindPort() : int
    {
        return (int) explode(':', $this->tcpBindAddressPort(), 2)[1];
    }

    public function toString() : string
    {
        return $this->path;
    }
}
