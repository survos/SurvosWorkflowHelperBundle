<?php

namespace Survos\WorkflowBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder

    {
        $treeBuilder = new TreeBuilder('survos_workflow_bundle');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('direction')->defaultValue('LR')->end()
                ->scalarNode('base_layout')->defaultValue('base.html.twig')->end()
                ->arrayNode('entities')
                    ->scalarPrototype()->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}

