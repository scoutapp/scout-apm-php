<?php

declare(strict_types=1);

namespace Scoutapm\ScoutApmBundle\DependencyInjection;

use Scoutapm\Config\ConfigKey;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function method_exists;

final class Configuration implements ConfigurationInterface
{
    private const ROOT_NODE_NAME = 'scout_apm';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        /** @psalm-suppress TooManyArguments */
        $treeBuilder = new TreeBuilder(self::ROOT_NODE_NAME);

        /** @psalm-suppress PossiblyUndefinedMethod analysis failures are down to annotations upstream */
        $children = $this->crossCompatibleRootNode($treeBuilder)
            ->children()
                ->arrayNode('scoutapm')
                    ->children();

        foreach (ConfigKey::allConfigurationKeys() as $configKey) {
            $children = $children->scalarNode($configKey)->defaultNull()->end();
        }

                    $children->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    /** @return NodeDefinition|ArrayNodeDefinition */
    private function crossCompatibleRootNode(TreeBuilder $treeBuilder): NodeDefinition
    {
        /** @noinspection ClassMemberExistenceCheckInspection */
        if (method_exists($treeBuilder, 'getRootNode')) {
            return $treeBuilder->getRootNode();
        }

        /**
         * @psalm-suppress DeprecatedMethod newer SF versions have the getRootNode method, so won't reach here
         * @psalm-suppress UndefinedMethod even newer SF versions remove this method entirely, but shouldn't reach here
         */
        return $treeBuilder->root(self::ROOT_NODE_NAME);
    }
}
