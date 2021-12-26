<?php

namespace Survos\WorkflowBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class SurvosWorkflowExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('survos_workflow.direction', $config['direction']);
        $container->setParameter('survos_workflow.base_layout', $config['base_layout']);
        $container->setParameter('survos_workflow.entities', $config['entities']);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config/'));
        $loader->load('services.xml');
    }

    public function getAlias(): string
    {
        return 'survos_workflow';
    }
}
