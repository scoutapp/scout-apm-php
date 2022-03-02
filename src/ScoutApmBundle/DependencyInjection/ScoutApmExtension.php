<?php

declare(strict_types=1);

namespace Scoutapm\ScoutApmBundle\DependencyInjection;

use Exception;
use Scoutapm\ScoutApmAgent;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

use function array_key_exists;

/** @internal This class extends a third party vendor, so we mark as internal to not expose upstream BC breaks */
final class ScoutApmExtension extends Extension
{
    /**
     * @throws Exception
     *
     * @inheritDoc
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('scoutapm.xml');

        $definition = $container->getDefinition(ScoutApmAgent::class);
        $definition->replaceArgument(
            '$agentConfiguration',
            array_key_exists('scoutapm', $config) ? $config['scoutapm'] : []
        );
    }
}
