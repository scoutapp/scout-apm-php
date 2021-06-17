<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\ScoutApmBundle;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Scoutapm\Config\ConfigKey;
use Scoutapm\Connector\Connector;
use Scoutapm\Events\Metadata;
use Scoutapm\Extension\ExtensionCapabilities;
use Scoutapm\ScoutApmBundle\ScoutApmAgentFactory;

use function json_decode;
use function json_encode;

/** @covers \Scoutapm\ScoutApmBundle\ScoutApmAgentFactory */
final class ScoutApmAgentFactoryTest extends TestCase
{
    public function testFactoryConfiguresFrameworkNameAndVersion(): void
    {
        $logger       = $this->createMock(LoggerInterface::class);
        $cache        = $this->createMock(CacheInterface::class);
        $connector    = $this->createMock(Connector::class);
        $phpExtension = $this->createMock(ExtensionCapabilities::class);

        $connector->expects(self::at(3))
            ->method('sendCommand')
            ->with(self::callback(static function (Metadata $metadata) {
                $flattenedMetadata = json_decode(json_encode($metadata), true)['ApplicationEvent']['event_value'];

                self::assertArrayHasKey('framework', $flattenedMetadata);
                self::assertSame('Symfony', $flattenedMetadata['framework']);

                self::assertArrayHasKey('framework_version', $flattenedMetadata);
                self::assertNotSame('', $flattenedMetadata['framework_version']);

                return true;
            }));

        $agent = ScoutApmAgentFactory::createAgent(
            $logger,
            $cache,
            $connector,
            $phpExtension,
            [
                ConfigKey::APPLICATION_NAME => 'Symfony Agent Factory Test',
                ConfigKey::APPLICATION_KEY => 'test application key',
                ConfigKey::MONITORING_ENABLED => true,
            ]
        );

        $agent->send();
    }
}
