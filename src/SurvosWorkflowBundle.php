<?php
namespace Survos\WorkflowBundle;

use Survos\WorkflowBundle\Controller\WorkflowController;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class SurvosWorkflowBundle extends AbstractBundle
{

    // $config is the bundle Configuration that you usually process in ExtensionInterface::load() but already merged and processed
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $loader = new XmlFileLoader($builder, new FileLocator(__DIR__ . '/Resources/config/'));
        $loader->load('services.xml');


//        $definition = $builder->getDefinition('survos.foo');
//        $builder->autowire('survos.foo_twig', FooTwigExtension::class)
//            ->addTag('twig.extension');

//        $serviceIdentifier = 'survos.foo';
//        $definition = $builder->autowire(WorkflowController::class, WorkflowController::class)
//            ->addTag('controller.service_argument');
//        $definition->addTag('container.service_subscriber');

//        $definition->setPublic(true);
//        $container->services()->alias(FooService::class, $serviceIdentifier);
//        $definition->setArgument(0, $config['title']);

        $builder->setParameter('survos_workflow.direction', $config['direction']);
        $builder->setParameter('survos_workflow.base_layout', $config['base_layout']);

    }

        public function configure(DefinitionConfigurator $definition): void
    {
//        // loads config definition from a file
//        $definition->import('../config/definition.php');
//
//        // loads config definition from multiple files (when it's too long you can split it)
//        $definition->import('../config/definition/*.php');

        // if the configuration is short, consider adding it in this class
        $definition->rootNode()
            ->children()
                ->scalarNode('direction')->defaultValue('LR')->end()
                ->scalarNode('base_layout')->defaultValue('base.html.twig')->end()
                ->arrayNode('entities')
                    ->scalarPrototype()->end()
                ->end()
            ->end();

        ;
    }
}
