<?php

namespace Survos\WorkflowBundle;

use JetBrains\PhpStorm\NoReturn;
use Survos\CoreBundle\Traits\HasAssetMapperTrait;
use Survos\WorkflowBundle\Command\ConvertFromYamlCommand;
use Survos\WorkflowBundle\Command\IterateCommand;
use Survos\WorkflowBundle\Command\SurvosWorkflowConfigureCommand;
use Survos\WorkflowBundle\Command\SurvosWorkflowDumpCommand;
use Survos\WorkflowBundle\Controller\WorkflowController;
use Survos\WorkflowBundle\Doctrine\TransitionListener;
use Survos\WorkflowBundle\Service\ConfigureFromAttributesService;
use Survos\WorkflowBundle\Service\WorkflowHelperService;
use Survos\WorkflowBundle\Twig\WorkflowExtension;
use Survos\WorkflowHelperBundle\Attribute\Workflow;
use Symfony\Bundle\FrameworkBundle\Command\WorkflowDumpCommand;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Workflow\Registry;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

class SurvosWorkflowBundle extends AbstractBundle implements CompilerPassInterface
{
    use HasAssetMapperTrait;
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Register this class as a pass, to eliminate the need for the extra DI class
        // https://stackoverflow.com/questions/73814467/how-do-i-add-a-twig-global-from-a-bundle-config
        $container->addCompilerPass($this);
    }

    public function process(ContainerBuilder $container): void
    {

        // for now, look for workflows defined in config/packages/workflow.php
        // @todo: scan for classes that implement SurvosWorkflowInterface or something


        $configs = $container->getExtensionConfig('framework');

        $configuration = $container
            ->getExtension('framework')
            ->getConfiguration($configs, $container)
        ;

        $config = (new Processor())->processConfiguration($configuration, $configs);
        $workflowConfig = $config['workflows']['workflows'] ?? [];
        $container->setParameter('workflows.configuration', $workflowConfig);
//        dd($workflowConfig, $config, $configs, $configuration);
        // set enabled transitions from the database.
        $transitionListenerDefinition = $container->findDefinition(TransitionListener::class);
        $transitionListenerDefinition->setArgument('$workflowHelperService', new Reference(WorkflowHelperService::class));
        $transitionListenerDefinition->setArgument('$workflows', tagged_iterator('workflow'));

        $workflowHelperDefinition = $container->findDefinition(WorkflowHelperService::class);
        $workflowHelperDefinition->setArgument('$configuration', $workflowConfig);
//        $workflowHelperDefinition->setArgument('$workflows', tagged_iterator('workflow'));

        $container->findDefinition(SurvosWorkflowDumpCommand::class)
            ->setArgument('$workflows', tagged_iterator('workflow'));

        foreach (tagged_iterator('workflow', 'name') as $x) {
            dd($x);
        }
//        dd(tagged_iterator('workflow'));


    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {

        $builder->autowire( WorkflowHelperService::class)
//            ->setArgument('$workflowRegistry', new Reference('workflow.registry'))
            ->setAutoconfigured(true)
        ;
//            ->setArgument('$locator', ServiceLocatorTagPass::register($builder, $locateableServices))
//            ->setArgument('$direction', $config['direction'])
//            ->setArgument('$em', new Reference('doctrine.orm.entity_manager'))
        ;

        $builder->setParameter('survos_workflow.base_layout', $config['base_layout']);
        // hopefully not needed!
//        $builder->setParameter('survos_workflow.entities', $config['entities']);

        $container->services()
            ->set('console.command.workflow_dump', WorkflowDumpCommand::class)
            ->args([
                tagged_locator('workflow', 'name'),
            ]);


        //        $builder->register('workflow.registry', Registry::class); // isn't this already done by Symfony/Workflow

        //        $builder->register('survos_workflow_bundle.workflow_helper', WorkflowHelperService::class);

//        $workflowHelperId = 'survos_workflow_bundle.workflow_helper';
//        $container->services()->alias($workflowHelperId, WorkflowHelperService::class);
//        $workflowHelperService = $container->getDefinition(WorkflowHelperService::class);

//        $workflowHelperService->setArgument('$locator', tagged_locator(tag: 'workflow', indexAttribute: 'name' ))
        $locatableServices = ['workflow' => new Reference('workflow')];


        $builder->autowire(TransitionListener::class);

        $builder->autowire(WorkflowExtension::class)
            ->addArgument(new Reference(WorkflowHelperService::class))
            ->addTag('twig.extension');

        $builder->autowire(SurvosWorkflowDumpCommand::class)
            ->addArgument(new Reference(WorkflowHelperService::class))
            ->addArgument(new Reference('translator'))
            ->addTag('console.command')
        ;

        $builder->autowire(IterateCommand::class)
            ->setAutoconfigured(true)
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


        $builder->autowire(WorkflowController::class)
            ->addTag('container.service_subscriber')
            ->addTag('controller.service_arguments')
            ->setAutoconfigured(true)
            ->setPublic(true)
        ;

        $builder->autowire(ConfigureFromAttributesService::class)
            ->setAutoconfigured(true)
            ->setPublic(true)
        ;

//        $builder->autowire(Workflow::class)
//            ->setPublic(true);
//
        $builder->autowire(SurvosWorkflowConfigureCommand::class, SurvosWorkflowConfigureCommand::class)
            ->addTag('console.command')
            ->addArgument('%kernel.project_dir%')
        ;

    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
            ->scalarNode('base_layout')->defaultValue('base.html.twig')->end()
            ->arrayNode('entities')
            ->scalarPrototype()
            ->end()->end()
            ->end();
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$this->isAssetMapperAvailable($builder)) {
            return;
        }


        $dir = realpath(__DIR__.'/../assets/');
        assert(file_exists($dir), $dir);

        $builder->prependExtensionConfig('framework', [
            'asset_mapper' => [
                'paths' => [
                    $dir => '@survos/workflow-helper',
                ],
            ],
        ]);
    }

}
