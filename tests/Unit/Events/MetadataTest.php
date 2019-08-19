<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\Events;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use function gethostname;
use function json_decode;
use function json_encode;
use PHPUnit\Framework\TestCase;
use Scoutapm\Agent;
use Scoutapm\Events\Metadata;
use Scoutapm\Helper\Timer;

/** @covers \Scoutapm\Events\Metadata */
final class MetadataTest extends TestCase
{
    /** @throws Exception */
    public function testMetadataSerializesToJson() : void
    {
        $agent = $this->createMock(Agent::class);
        $time = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $serialized = json_encode(new Metadata($agent, $time));

        self::assertNotEmpty($serialized);

        self::assertEquals(
            [
                'ApplicationEvent' => [
                    'timestamp' => $time->format(Timer::FORMAT_FOR_CORE_AGENT),
                    'event_value' => [
                        'language' => 'php',
                        'version' => PHP_VERSION,
                        'server_time' => $time->format(Timer::FORMAT_FOR_CORE_AGENT),
                        'framework' => 'laravel',
                        'framework_version' => '',
                        'environment' => '',
                        'app_server' => '',
                        'hostname' => gethostname(),
                        'database_engine' => '',
                        'database_adapter' => '',
                        'application_name' => '',
                        'libraries' => [],
                        'paas' => '',
                        'application_root' => '',
                        'scm_subdirectory' => '',
                        'git_sha' => '',
                    ],
                    'event_type' => 'scout.metadata',
                    'source' => 'php',
                ],
            ],
            json_decode($serialized, true)
        );
    }
}
