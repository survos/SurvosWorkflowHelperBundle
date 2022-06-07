<?php
namespace Survos\WorkflowBundle;

use Survos\WorkflowBundle\Command\SurvosWorkflowConfigureCommand;
use Survos\WorkflowBundle\Command\SurvosWorkflowDumpCommand;
use Survos\WorkflowBundle\Controller\WorkflowController;
use Survos\WorkflowBundle\Service\WorkflowHelperService;
use Survos\WorkflowBundle\Twig\WorkflowExtension;
use Symfony\Bundle\FrameworkBundle\Command\WorkflowDumpCommand;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\Reference;

class SurvosWorkflowBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('survos_workflow.direction', $config['direction']);
        $builder->setParameter('survos_workflow.base_layout', $config['base_layout']);
        $builder->setParameter('survos_workflow.entities', $config['entities']);

//        $container->import('../config/routes.xml');
//        $builder->register('survos_workflow_bundle.workflow_helper', WorkflowHelperService::class);

        $workflowHelperId = 'survos_workflow_bundle.workflow_helper';
        $container->services()->alias(WorkflowHelperService::class, $workflowHelperId);
        $builder->autowire($workflowHelperId, WorkflowHelperService::class)
            ->addArgument($config['direction'])
            ->addArgument(new Reference('doctrine.orm.entity_manager'))
            ->addArgument(new Reference('workflow.registry'))
        ;

        $builder->autowire(WorkflowExtension::class)
            ->addArgument(new Reference($workflowHelperId))
            ->addTag('twig.extension');



        $builder->autowire(SurvosWorkflowDumpCommand::class)
            ->addArgument(new Reference($workflowHelperId))
            ->addArgument(new Reference('translator'))
            ->addArgument(new Reference('workflow.registry'))
            ->addTag('console.command')
        ;

        $builder->autowire(WorkflowController::class)
            ->addArgument(new Reference($workflowHelperId))
            ->addArgument(new Reference('translator'))
            ->addArgument(new Reference('workflow.registry'))
            ->addTag('container.service_subscriber')
            ->addTag('controller.service_argument')
            ->setPublic(true)
        ;

        $builder->autowire(SurvosWorkflowConfigureCommand::class, SurvosWorkflowConfigureCommand::class)
            ->addTag('console.command')
            ->addArgument('%kernel.project_dir%')
            ;

//        $container->import('../config/services.xml');

    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
            ->scalarNode('direction')->defaultValue('LR')->end()
            ->scalarNode('base_layout')->defaultValue('base.html.twig')->end()
            ->arrayNode('entities')
            ->scalarPrototype()
            ->end()->end()
//            ->booleanNode('unicorns_are_real')->defaultTrue()->end()
//            ->integerNode('min_sunshine')->defaultValue(3)->end()
            ->end();
    }

}
