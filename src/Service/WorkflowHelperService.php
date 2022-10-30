<?php

namespace Survos\WorkflowBundle\Service;

use App\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;
use Survos\BootstrapBundle\Traits\QueryBuilderHelperInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Process\Process;
use Symfony\Component\Workflow\Dumper\GraphvizDumper;
use Symfony\Component\Workflow\Dumper\StateMachineGraphvizDumper;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\SupportStrategy\InstanceOfSupportStrategy;
use Symfony\Component\Workflow\Workflow;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Generate Workflow Diagrams
 *
 * @author Tac Tacelosky <tacman@gmail.com>
 */
class WorkflowHelperService
{
    private $dumper;

//    /** @var WorkflowInterface[] */
////    private array $workflows = [];
//
//    public function __construct(iterable $workflows)
//    {
//        foreach ($workflows as $name => $workflow) {
//            // You can dump($workflow); here
//            dd($workflow);
//            $this->workflows[$name] = $workflow;
//        }
//    }

    public function __construct(
        /** @var WorkflowInterface[] */
        private iterable $workflows,

//        private ServiceLocator $locator,
//        private string $direction,
        private EntityManagerInterface $em,
        private ?Registry $workflowRegistry = null,
    ) {
        // get the workflows from the registry:
        $reflectionProperty = new \ReflectionProperty(get_class($this->workflowRegistry), 'workflows');
        $workflowCountFromRegistry = count($reflectionProperty->getValue($this->workflowRegistry));


//        foreach ($workflows as $name => $workflow) {
//            // You can dump($workflow); here
//            $this->workflows[$name] = $workflow;
//            dd($workflow);
//        }
//        assert(count($this->workflows) == $workflowCountFromRegistry, sprintf("$workflowCountFromRegistry workflows in registry, %d from the service_iterator",
//        count($this->workflows)));
//        dd($this->locator->count(), count($this->workflowRegistry->all(new Task())));
        $this->dumper = new SurvosStateMachineGraphVizDumper();
    }

    /**
     * @deprecated
     * @return Registry
     */
    public function getRegistry()
    {
        return $this->workflowRegistry;
    }

    public function setRegistry(Registry $registry) {
        $this->workflowRegistry = $registry;
    }

    // @idea: pass in the repository to make the counts call.
    public function getMarkingData(WorkflowInterface $workflow, string $class, array $counts = null): array
    {
        $repo = $this->em->getRepository($class);
        if (empty($counts)) {
            if (method_exists($repo, 'findBygetCountsByField')) {
                $counts = $repo->findBygetCountsByField('marking'); //
            } else {
                throw new \Exception("Marking data requires as findBygetCountsByField in the repository, use QueryBuilderHelperInterface from BaseBundle");
            }
        }
        return array_map(fn ($marking) =>
            array_merge([
                'marking' => $marking,
                'count' => $counts[$marking] ?? null,
            ], $workflow->getMetadataStore()->getPlaceMetadata($marking)), $workflow->getDefinition()->getPlaces());
    }

    public function getWorkflow($subject, string $workflowName): WorkflowInterface
    {

        /** @var WorkflowInterface $workflow */
        try {
            $workflow = $this->workflowRegistry->get($subject, $workflowName);
        } catch (\Exception $e) {
            return $e->getMessage(); // null;
        }
        return $workflow;
    }

    public function workflowConstants(WorkflowInterface $workflow)
    {

//        $workflow = $this->getWorkflow($subject, $workflowName);

        // dump workflow constants with attributes
        $definition = $workflow->getDefinition();
        $workflowPlaces = $workflow->getDefinition()->getPlaces();

        foreach ($workflowPlaces as $code=>$value) {

        }

        dd($workflowPlaces);



    }

    /**
     * @param $subject
     * @param $workflowName
     * @return string
     */
    public function workflowDiagramDigraph($subject, string $workflowName, ?string $direction = null)
    {
        if ($direction) {
            $this->direction = $direction;
        }
        $workflow = $this->getWorkflow($subject, $workflowName);
        $definition = $workflow->getDefinition();
        $workflowPlaces = $workflow->getDefinition()->getPlaces();

        $markingStore = $workflow->getMarkingStore();
        $entityPlaces = $workflow->getDefinition()->getPlaces();
//        dd($places);
//        $entityPlaces = array_keys($workflow->getMarkingStore()->getMarking($subject)->getPlaces());
        try {
            $marking = $workflow->getMarkingStore()->getMarking($subject);
            array_map(function ($place) use ($marking) {
                if ($marking->has($place)) {
                    $marking->unmark($place);
                }
            }, $workflowPlaces);

            // set it to the subject markings
            array_map(
                function ($place) use ($marking) {
                    $marking->mark($place);
                },
                $entityPlaces
            );

        } catch (\Exception $exception) {
            $initial = $workflow->getDefinition()->getInitialPlaces()[0];
            $marking = (new Marking())
                ->mark($subject->marking ?? $initial);

        }

        // unset anything previously set
        $dot = $this->dumper->dump($definition, $marking, [
            //graphviz docs http://www.graphviz.org/doc/info/attrs.html
            'graph' => [
                'ratio' => 'compress',
                'width' => 0.5,
                'rankdir' => null, // $this->direction,
                'ranksep' => 0.2,
            ],
            'node' => [
                'width' => 0.5,
                'shape' => 'ellipse',
            ],
            'edge' => [
                'shape' => 'box',
                'arrowsize' => '0.5',
            ],
        ]);

        return $dot;
    }

        /**
         * @param $subject
         * @param $workflowName
         * @param string $direction LR or TB
         * @return string
         */
        public function workflowDiagram($subject, $workflowName, string $direction)
        {
            $dot = $this->workflowDiagramDigraph($subject, $workflowName, $direction);

            // dump($dot); die();

            try {
                $process = new Process(['dot', '-Tsvg']);
                $process->setInput($dot);
                $process->mustRun();

                $svg = $process->getOutput();
            } catch (\Exception $e) { //. if dot not installed
                // @todo: configure paths to the .svg files (for filesystem and url)
                $svg = sprintf("<!-- return a static svg if dot isn't working --><img src='/svg/%s.svg' />", $workflowName);
                // return $svg; // hack
                return $svg;
            }

            // @todo: set the cache path in the workflow.yaml config

            //  return sprintf("Workflow: <code>%s</code>%s", $workflowName, $svg);

            return $svg;
        }

    /**
     * @return <string, Workflow[]>
     */
    public function getWorkflowsGroupedByClass(): array
    {
        $reflectionProperty = new \ReflectionProperty(get_class($this->workflowRegistry), 'workflows');
        $workflowBlobs = $reflectionProperty->getValue($this->workflowRegistry);
        $workflowsByCode = [];

        /**
         * @var InstanceOfSupportStrategy $suportStrategy
         * @var StateMachine $stateMachine
         */
        foreach ($workflowBlobs as [$stateMachine, $suportStrategy]) {
            //            dump($stateMachine, $suportStrategy);
            $class = $suportStrategy->getClassName();
            if (empty($workflowsByCode[$class])) {
                $workflowsByCode[$class] = [];
            }
            $name = $stateMachine->getName();
            $workflowsByCode[$class][$name] = $stateMachine;
        }
        return $workflowsByCode;
    }

    public function getWorkflowsByCode($code = null)
    {
        $registry = $this->workflowRegistry;

        $reflectionProperty = new \ReflectionProperty(get_class($this->workflowRegistry), 'workflows');
        $workflowBlobs = $reflectionProperty->getValue($this->workflowRegistry);
        $workflowsByCode = [];

        foreach ($workflowBlobs as $workflowBlob) {
            /** @var StateMachine $x */
            $x = $workflowBlob[0];

            $class = $workflowBlob[1]->getClassName();
            // @todo: use a Factory
            $entity = new $class();
            $flowCode = $x->getName();
            /** @var Workflow $workflow */

            $workflow = $registry->get($entity, $flowCode);
            $places = $workflow->getDefinition()->getPlaces();

            $workflowsByCode[$flowCode] =
                [
                    'initialPlace' => $places, // ??
                    'workflow' => $workflow,
                    'class' => $class,
                    'entity' => $entity,
                    'definition' => $x->getDefinition(),
                ];
        }
        return $code ? $workflowsByCode[$code] : $workflowsByCode;
    }
}
