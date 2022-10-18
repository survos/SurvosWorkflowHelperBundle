<?php

namespace Survos\WorkflowBundle;

use Survos\WorkflowBundle\Command\ConvertFromYamlCommand;
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
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Workflow\Registry;

class SurvosWorkflowBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('survos_workflow.direction', $config['direction']);
        $builder->setParameter('survos_workflow.base_layout', $config['base_layout']);
        $builder->setParameter('survos_workflow.entities', $config['entities']);

        //        $builder->register('workflow.registry', Registry::class); // isn't this already done by Symfony/Workflow

        //        $builder->register('survos_workflow_bundle.workflow_helper', WorkflowHelperService::class);

        $workflowHelperId = 'survos_workflow_bundle.workflow_helper';
        $container->services()->alias($workflowHelperId, WorkflowHelperService::class);
        $builder->autowire( WorkflowHelperService::class)
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

//        $serivceId = 'survos_command.command_controller';
//        $container->services()->alias(CommandController::class, $serivceId);
//        $builder->autowire(CommandController::class)
//            ->setArgument('$kernel', new Reference('kernel'))
//            ->addTag('container.service_subscriber')
//            ->addTag('controller.service_arguments')
//            ->setPublic(true)
//            ->setAutoconfigured(true)
        ;

//        $workflowControllerId = 'survos_workflow_bundle.workflow_controller';
//        $container->services()->alias(WorkflowController::class, $workflowControllerId  );
        //        $builder->register($workflowControllerId, WorkflowController::class);
//        $builder->autowire(WorkflowController::class)
//            ->setArgument('$helper', new Reference($workflowHelperId))
//            ->addTag('container.service_subscriber')
//            ->addTag('controller.service_arguments')
        $builder->autowire(WorkflowController::class)
            ->setAutoconfigured(true)
            ->setPublic(true)
        ;

        $builder->autowire(SurvosWorkflowConfigureCommand::class, SurvosWorkflowConfigureCommand::class)
            ->addTag('console.command')
            ->addArgument('%kernel.project_dir%')
        ;

        $builder->autowire(ConvertFromYamlCommand::class)
            ->addTag('console.command')
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
