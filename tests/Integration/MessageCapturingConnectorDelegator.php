<?php

declare(strict_types=1);

namespace Scoutapm\IntegrationTests;

use Scoutapm\Connector\Connector;
use Scoutapm\Connector\SerializableMessage;

final class MessageCapturingConnectorDelegator implements Connector
{
    /** @var SerializableMessage[] */
    public $sentMessages = [];

    /** @var Connector */
    private $delegate;

    public function __construct(Connector $delegate)
    {
        $this->delegate = $delegate;
    }

    public function connect() : void
    {
        $this->delegate->connect();
    }

    public function connected() : bool
    {
        return $this->delegate->connected();
    }

    public function sendMessage(SerializableMessage $message) : bool
    {
        $this->sentMessages[] = $message;

        return $this->delegate->sendMessage($message);
    }

    public function shutdown() : void
    {
        $this->delegate->shutdown();
    }
}
