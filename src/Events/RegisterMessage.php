<?php

declare(strict_types=1);

namespace Scoutapm\Events;

use Scoutapm\Connector\SerializableMessage;

/** @internal */
final class RegisterMessage implements SerializableMessage
{
    /** @var string */
    private $appName;

    /** @var string */
    private $appKey;

    /** @var string */
    private $apiVersion;

    public function __construct(string $appName, string $appKey, string $apiVersion)
    {
        $this->appName    = $appName;
        $this->appKey     = $appKey;
        $this->apiVersion = $apiVersion;
    }

    /** @return array<string, array<string, string>> */
    public function jsonSerialize() : array
    {
        return [
            'Register' => [
                'app' => $this->appName,
                'key' => $this->appKey,
                'language' => 'php',
                'api_version' => $this->apiVersion,
            ],
        ];
    }
}
