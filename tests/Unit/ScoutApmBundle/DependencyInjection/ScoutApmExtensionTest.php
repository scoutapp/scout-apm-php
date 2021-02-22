<?php

declare(strict_types=1);

namespace Scoutapm\UnitTests\ScoutApmBundle\DependencyInjection;

use Exception;
use PHPUnit\Framework\TestCase;
use Scoutapm\ScoutApmAgent;
use Scoutapm\ScoutApmBundle\DependencyInjection\ScoutApmExtension;
use Scoutapm\ScoutApmBundle\EventListener\InstrumentationListener;
use Scoutapm\ScoutApmBundle\ScoutApmAgentFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function array_filter;

/** @covers \Scoutapm\ScoutApmBundle\DependencyInjection\ScoutApmExtension */
final class ScoutApmExtensionTest extends TestCase
{
    /** @throws Exception */
    public function testLoadSetsUpDependencyInjectionConfiguration(): void
    {
        $builder = new ContainerBuilder();

        $scoutApmConfiguration = [
            'name' => 'My Symfony App',
            'key' => 'some application key',
            'monitor' => true,
        ];

        (new ScoutApmExtension())->load(
            [
                ['scoutapm' => $scoutApmConfiguration],
            ],
            $builder
        );

        self::assertTrue($builder->hasDefinition(ScoutApmAgent::class));
        $agentDefinition = $builder->getDefinition(ScoutApmAgent::class);

        self::assertSame([ScoutApmAgentFactory::class, 'createAgent'], $agentDefinition->getFactory());

        $agentConfigurationArgument = $agentDefinition->getArgument('$agentConfiguration');
        self::assertEquals($scoutApmConfiguration, array_filter($agentConfigurationArgument));

        self::assertTrue($builder->hasDefinition(InstrumentationListener::class));
        $listener = $builder->getDefinition(InstrumentationListener::class);

        self::assertEquals(['kernel.event_subscriber' => [[]]], $listener->getTags());
    }

    /** @throws Exception */
    public function testLoadPassesEmptyConfigurationAsFactoryParameterWhenNoConfigurationPassedToLoad(): void
    {
        $builder = new ContainerBuilder();

        (new ScoutApmExtension())->load([], $builder);

        self::assertTrue($builder->hasDefinition(ScoutApmAgent::class));

        $agentConfigurationArgument = $builder->getDefinition(ScoutApmAgent::class)->getArgument('$agentConfiguration');
        self::assertEquals([], $agentConfigurationArgument);

        self::assertTrue($builder->hasDefinition(InstrumentationListener::class));
    }
}
