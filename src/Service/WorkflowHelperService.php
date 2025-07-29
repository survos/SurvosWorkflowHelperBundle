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
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
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
        private EntityManagerInterface                                      $entityManager,
        private PropertyAccessorInterface $propertyAccessor,
        private array                                                       $configuration,
        private ?LoggerInterface                                            $logger = null,
    )
    {
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
        return array_map(fn($marking) => array_merge([
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

        foreach ($workflowPlaces as $code => $value) {

        }

//        dd($workflowPlaces);


    }

    /**
     * @param $subject
     * @param $workflowName
     * @return string
     */
    public function workflowDiagramDigraph($subject = null, ?string $workflowName = null)
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

        /**
         * @var InstanceOfSupportStrategy $suportStrategy
         * @var StateMachine $stateMachine
         */
        $workflowsByClass = [];

//        $x = $this->getWorkflowsIndexedByName();

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
    }

    public function getWorkflowByCode(string $code)
    {
        SurvosUtils::assertKeyExists($code, $this->getWorkflowsIndexedByName());
        return $this->getWorkflowsIndexedByName()[$code];
    }

    public function getCounts(string $class, string $field): array
    {
        $repo = $this->entityManager->getRepository($class);
        $results = $repo->createQueryBuilder('s')
            ->groupBy('s.' . $field)
            ->select(["s.$field, count(s) as count"])
            ->getQuery()
            ->getArrayResult();
        $counts = [];
        foreach ($results as $r) {
            assert(is_string($field));
            assert(is_array($r));
//            dump($field, $r, $r['count'], $r['field']);
            $key = $r[$field] ?? ''; // not really...
            if (is_array($key)) {
                continue; // doctrine can't handle arrays for facets, just scalars
                dd($key, $field, $r);
            }

            $count = $r['count'];
            assert(is_integer($key) || is_string($key), json_encode($key));
            assert(is_integer($count));
            $counts[$key] = $count;
        }
//        dd($counts);

        return $counts;
    }

    public function getApproxCount(string $class): ?int
    {
        static $counts = null;
        $repo = $this->entityManager->getRepository($class);

        try {
            if (is_null($counts)) {
                $rows = $this->entityManager->getConnection()->fetchAllAssociative(
                    "SELECT n.nspname AS schema_name,
       c.relname AS table_name,
       c.reltuples AS estimated_rows
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE c.relkind = 'r'
  AND n.nspname NOT IN ('pg_catalog', 'information_schema')  -- exclude system schemas
ORDER BY n.nspname, c.relname;");

                $counts = array_combine(
                    array_map(fn($r) => "{$r['table_name']}", $rows),
                    array_map(fn($r) => (int)$r['estimated_rows'], $rows)
                );
            }
            $count = $counts[$repo->getClassMetadata()->getTableName()] ?? -1;

//            // might be sqlite
//            $count =  (int) $this->getEntityManager()->getConnection()->fetchOne(
//                'SELECT reltuples::BIGINT FROM pg_class WHERE relname = :table',
//                ['table' => $this->getClassMetadata()->getTableName()]
//            );
        } catch (\Exception $e) {
            $count = -1;
        }

        // if no analysis
        // Fallback to exact count
        if ($count < 0) {
            $count = $repo->count();
//            // or $repo->count[]
//            $count = (int)$repo->createQueryBuilder('e')
//                ->select('COUNT(e)')
//                ->getQuery()
//                ->getSingleScalarResult();
        }

        return $count;
    }


    #[AsMessageHandler]
    // @todo: make sure this is property configured in SurvosWorkflowBundle
    public function handleTransition(TransitionMessage $message)
    {
        if (!$object = $this->entityManager->find($message->getClassName(), $message->getId())) {
            $debugMessage = sprintf("missing entity %s for %s", $message->getClassName(), $message->getId());
            return ['message' => $debugMessage];
        }

        $initialMarking = $object->getMarking(); // @todo: use Marking Service to handle more cases, e.g. ->marking
//        assert($object, $message);
        // removed, throw error (above) during testing only.
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
            'marking' => $marking,
            'class' => $object,
        ];

    }

}
