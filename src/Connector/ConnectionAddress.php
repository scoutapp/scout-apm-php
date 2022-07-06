<?php

declare(strict_types=1);

namespace Scoutapm\Connector;

use LogicException;
use Scoutapm\Config;
use Scoutapm\Config\ConfigKey;

use function array_key_exists;
use function explode;
use function sprintf;
use function strlen;
use function strpos;
use function substr;

/** @internal */
final class ConnectionAddress
{
    private const TCP_ADDRESS_MARKER = 'tcp://';

    private const DEFAULT_TCP_PORT    = 6590;
    private const DEFAULT_TCP_ADDRESS = '127.0.0.1';

    /** @var string */
    private $path;

    private function __construct(string $path)
    {
        $this->path = $path;
    }

    public static function fromConfig(Config $config): self
    {
        return new self($config->get(ConfigKey::CORE_AGENT_SOCKET_PATH));
    }

    public function isTcpAddress(): bool
    {
        return strpos($this->path, self::TCP_ADDRESS_MARKER) === 0;
    }

    public function isSocketPath(): bool
    {
        return ! $this->isTcpAddress();
    }

    public function socketPath(): string
    {
        if (! $this->isSocketPath()) {
            throw new LogicException('Cannot extract socket path from a non-socket address');
        }

        return $this->path;
    }

    /**
     * @return string[]
     * @psalm-return list<string>
     */
    private function explodeTcpAddress(): array
    {
        if (! $this->isTcpAddress()) {
            throw new LogicException('Cannot extract TCP address from a non-TCP address');
        }

        return explode(':', substr($this->path, strlen(self::TCP_ADDRESS_MARKER)));
    }

    public function tcpBindAddressPort(): string
    {
        return sprintf('%s:%d', $this->tcpBindAddress(), $this->tcpBindPort());
    }

    public function tcpBindAddress(): string
    {
        $parts = $this->explodeTcpAddress();

        if (! array_key_exists(0, $parts) || $parts[0] === '') {
            return self::DEFAULT_TCP_ADDRESS;
        }

        return $parts[0];
    }

    public function tcpBindPort(): int
    {
        $parts = $this->explodeTcpAddress();

        if (! array_key_exists(1, $parts) || $parts[1] === '') {
            return self::DEFAULT_TCP_PORT;
        }

        return (int) $parts[1];
    }

    public function toString(): string
    {
        return $this->path;
    }
}
