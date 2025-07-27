<?php

namespace Survos\WorkflowBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Survos\CoreBundle\Traits\QueryBuilderHelperInterface;
use Survos\CoreBundle\Service\SurvosUtils;
use Survos\WorkflowBundle\Message\TransitionMessage;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
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
    private string $direction = 'TB'; // TB, BT

    public function __construct(
        /** @var WorkflowInterface[] */
        #[AutowireLocator('workflow.state_machine')] private ServiceLocator $workflows,
        private EntityManagerInterface $entityManager,
        private array $configuration,
        private ?LoggerInterface $logger = null,
    ) {
        $this->dumper = new SurvosStateMachineGraphVizDumper();
    }

    private function getWorkflowsFromTaggedIterator(): iterable
    {
        return $this->workflows;
    }

    /** @return array<string,WorkflowInterface> */
    public function getWorkflowsIndexedByName(): array
    {
//        static $workflows=[];
        $workflows = [];
            foreach ($this->workflows as $workflow) {
                $workflows[$workflow->getName()] = $workflow;
            }
        return $workflows;
    }

    public function getWorkflowConfiguration(): array
    {
        return $this->configuration;
    }


    // @idea: pass in the repository to make the counts call.
    public function getMarkingData(WorkflowInterface $workflow, string $class, ?array $counts = null): array
    {
        $repo = $this->entityManager->getRepository($class);
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

    public function getTransitionMetadata(string $transitionName, WorkflowInterface $workflow): array
    {
        // Get all transitions
        $transitions = $workflow->getDefinition()->getTransitions();

        foreach ($transitions as $transition) {
            if ($transition->getName() === $transitionName) {
                // Fetch metadata for this specific transition
                return $workflow->getMetadataStore()->getTransitionMetadata($transition);
            }
        }

        throw new \InvalidArgumentException(sprintf('Transition "%s" not found in the workflow.', $transitionName));
    }


    public function getWorkflow($subject, string $workflowName): WorkflowInterface
    {

        SurvosUtils::assertKeyExists($workflowName, $this->getWorkflowsIndexedByName());
        return $this->getWorkflowsIndexedByName()[$workflowName];

//        /** @var WorkflowInterface $workflow */
//        try {
//            $workflow = $this->workflowRegistry->get($subject, $workflowName);
//        } catch (\Exception $e) {
//            return $e->getMessage(); // null;
//        }
//        return $workflow;
    }

    public function workflowConstants(WorkflowInterface $workflow)
    {

//        $workflow = $this->getWorkflow($subject, $workflowName);

        // dump workflow constants with attributes
        $definition = $workflow->getDefinition();
        $workflowPlaces = $workflow->getDefinition()->getPlaces();

        foreach ($workflowPlaces as $code=>$value) {

        }

//        dd($workflowPlaces);



    }

    /**
     * @param $subject
     * @param $workflowName
     * @return string
     */
    public function workflowDiagramDigraph($subject=null, ?string $workflowName=null)
    {
        if ($subject) {
            $workflow = $this->getWorkflow($subject, $workflowName);
        } else {
            $workflow = $this->getWorkflowByCode($workflowName);
        }
        $definition = $workflow->getDefinition();
        $workflowPlaces = $workflow->getDefinition()->getPlaces();

        $markingStore = $workflow->getMarkingStore();
        $entityPlaces = $workflow->getDefinition()->getPlaces();
//        dd($places);
//        $entityPlaces = array_keys($workflow->getMarkingStore()->getMarking($subject)->getPlaces());
        if ($subject) {
            $marking = $workflow->getMarkingStore()->getMarking($subject);
            try {
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
                (new Marking())->mark($subject->marking ?? $initial);
            }
        } else {
            $marking = new Marking();
            // if there's no subject, just use the initial marking, unless something is passed in
//            $marking = $definition->getInitialPlaces()[0];
            $initial = $workflow->getDefinition()->getInitialPlaces()[0];
            $marking->mark($subject->marking ?? $initial);
        }

        // unset anything previously set
        $dot = $this->dumper->dump($definition, $marking, [
            //graphviz docs http://www.graphviz.org/doc/info/attrs.html
            'graph' => [
                'ratio' => 'compress',
                'width' => 0.5,
                'rankdir' => $this->direction,
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
         * @return string
         */
        public function workflowDiagram($subject, $workflowName)
        {
            $dot = $this->workflowDiagramDigraph($subject, $workflowName);

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
    public function getSupportsIndexedByCode(): array
    {
        $classesByCode = [];
        foreach ($this->configuration as $workflowName => $config) {
            $classesByCode[$workflowName] = $config['supports'];
        }
        return $classesByCode;
    }

    public function getSupports(string $code): array
    {
        return $this->getSupportsIndexedByCode()[$code];
    }

    public function getWorkflowsGroupedByClass(): array
    {

//        /** @var Workflow $x */
//        foreach ($this->getWorkflowsFromTaggedIterator() as $y=>$x) {
//            dump($x, $x->getMarkingStore(), $x->getDefinition());
////            $reflectionProperty = new \ReflectionProperty(get_class($x), 'supports');
////            $supports = $reflectionProperty->getValue($x);
//            dd($y, $x);
//            dump($x, $x->getMarkingStore(), $x->getDefinition());
//        }

        /**
         * @var InstanceOfSupportStrategy $suportStrategy
         * @var StateMachine $stateMachine
         */
        $workflowsByClass = [];

        $x = $this->getWorkflowsIndexedByName();

        foreach ($this->configuration as $workflowName => $config) {
            $classes = $config['supports'];
            foreach ($classes as $class) {
                if (empty($workflowsByClass[$class])) {
                    $workflowsByClass[$class] = [];
                }
//                assert(array_key_exists($workflowName, $x), "$workflowName is missing in workflows " . join(',', array_keys($x)));
                $workflowsByClass[$class][] = $workflowName;
            }
        }
        return $workflowsByClass;
        dd($workflowsByClass, $x, $this->workflows, $this->configuration);


        $reflectionProperty = new \ReflectionProperty(get_class($this->workflowRegistry), 'workflows');
        $workflowBlobs = $reflectionProperty->getValue($this->workflowRegistry);
        foreach ($workflowBlobs as [$stateMachine, $suportStrategy]) {
            $class = $suportStrategy->getClassName();
//            dd($this->configuration, $workflowBlobs, $class, $stateMachine, $suportStrategy);

//            $name = $stateMachine->getName();
            $workflowsByClass[$class][$workflowName] = $x[$workflowName];
        }
    }

    public function getWorkflowByCode(string $code)
    {
        SurvosUtils::assertKeyExists($code, $this->getWorkflowsIndexedByName());
        return $this->getWorkflowsIndexedByName()[$code];
    }

    #[AsMessageHandler]
    // @todo: make sure this is property configured in SurvosWorkflowBundle
    public function handleTransition(TransitionMessage $message)
    {
        $object = $this->entityManager->find($message->getClassName(), $message->getId());
        $initialMarking = $object->getMarking(); // @todo: use Marking Service to handle more cases, e.g. ->marking
        $debugMessage = sprintf( "missing entity %s for %s", $message->getClassName(), $message->getId());
//        assert($object, $message);
        // removed, throw error (above) during testing only.
        if (!$object) {
            return ['message' => $debugMessage];
        }
        if (!$flowName = $message->getWorkflow()) {
            // ..
        }

        $shortName = new \ReflectionClass($message->getClassName())->getShortName();
        $id = $message->getId();

        $transition = $message->getTransitionName();
        $workflow = $this->getWorkflow($object, $flowName);
        if ($workflow->can($object, $transition)) {
            $marking = $workflow->apply($object, $transition, $message->getContext());
            // is this the best place to flush?  or only if workflow applied
            $this->entityManager->flush(); // save the marking and any updates
        } else {
            foreach ($workflow->buildTransitionBlockerList($object, $transition) as $blocker) {
                $this->logger->info($blocker->getMessage());
            }
            return [
                'info' => "cannot $transition $shortName::$id ",
                'message' => $blocker->getMessage(),
                'initialMarking' => $initialMarking,
                'class' => $message->getClassName(),
            ];
        }

        return [
            'message' => "applied $transition to $shortName::$id ($initialMarking)",
            //     'details' => json_encode((array)$message),
            'initialMarking' => $initialMarking,
            'marking' => $object->getMarking(),
            'class' => $object,
        ];

    }

}
