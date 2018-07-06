<?php

namespace Survos\WorkflowBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()

    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('survos_workflow_bundle');
        $rootNode
            ->children()
            ->scalarNode('direction')->defaultValue('LR')->end()
            ->arrayNode('entities')
                ->scalarPrototype()
            ->end()->end()
//            ->booleanNode('unicorns_are_real')->defaultTrue()->end()
//            ->integerNode('min_sunshine')->defaultValue(3)->end()
            ->end();

        return $treeBuilder;
    }
}
