<?php

namespace Survos\WorkflowBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Survos\CoreBundle\Traits\QueryBuilderHelperInterface;
use Survos\WorkflowBundle\Service\WorkflowHelperService;
use Survos\WorkflowBundle\Traits\MarkingInterface;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use Zenstruck\Collection\Doctrine\ORM\Bridge\ORMServiceEntityRepository;

class WorkflowController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface  $entityManager,
        protected WorkflowHelperService $workflowHelperService,
        private SerializerInterface     $serializer,
        private ?ChartBuilderInterface  $chartBuilder = null,


    ) // , private Registry $registry)
    {
//        foreach ($this->tagged->getIterator() as $workflow) {
//            dd($workflow);
//        }
//        dd($this->tagged);
        // $helper = $this->container->get('survos_workflow_bundle.workflow_helper'); // hmm, doesn't seem right.
        $this->workflowHelperService = $workflowHelperService;
        // $this->workflowRegistry = $this->get('workflow.registry'); // $helper->getRegistry();
    }

    #[Route("/", name: "survos_workflows")]
    public function workflows(Request $request): Response
    {
        $workflowsGroupedByCode = $this->workflowHelperService->getWorkflowsIndexedByName();
        $workflowsGroupedByClass = $this->workflowHelperService->getWorkflowsGroupedByClass();
        return $this->render("@SurvosWorkflow/index.html.twig", [
            'configs' => $this->workflowHelperService->getWorkflowConfiguration(),
            'workflowsGroupedByClass' => $workflowsGroupedByClass,
            'workflowsByCode' => $workflowsGroupedByCode,
        ]);
    }

    #[Route('/entities', name: 'survos_workflow_entities')]
    #[Template('@SurvosWorkflow/entities.html.twig')]
    public function entitiesGraph(): Response|array
    {
        $charts = [];

        /** @var QueryBuilderHelperInterface $class */
        foreach ($this->workflowHelperService->getWorkflowsGroupedByClass() as $class => $workflows) {
            /** @var QueryBuilderHelperInterface|ORMServiceEntityRepository $repo */
            $repo = $this->entityManager->getRepository($class);
//            $primaryKeyName = $this->entityManager->getClassMetadata($class)
//                ->getSingleIdentifierFieldName();
            // get the pk

//            dd(get_class($repo), $class, get_class_methods($repo));

            assert(method_exists($repo, 'getCounts'), $repo::class . '/' . $class);
//            dd($class, $workflows, $primaryKeyName);
//		foreach ([Inst::class, Coll::class, Obj::class, Link::class, Tag::class, Img::class, Euro::class, EuroObj::class] as $class) {
            $markingCounts = $repo->getCounts('marking');

            $workflow = $this->workflowHelperService->getWorkflowsGroupedByClass()[$class][0] ?? null;
            $total = $this->workflowHelperService->getApproxCount($class);
            $counts[$class] =
                [
                    'total' => $total,
                    'workflow' => $workflow,
                    'marking' => $markingCounts,
                ];


            //            $palette = ['#f87171', '#60a5fa', '#facc15'];


            $palette = [
                '#4E79A7', // Muted Blue
                '#F28E2B', // Warm Orange
                '#E15759', // Soft Red
                '#76B7B2', // Teal
                '#59A14F', // Green
                '#EDC948', // Yellow
                '#B07AA1', // Lavender
                '#FF9DA7', // Pink
                '#9C755F', // Brown
                '#BAB0AC'  // Gray
            ];

            $markings = array_keys($markingCounts);
            $colors = [];
            $workflowsByClass = $this->workflowHelperService->getWorkflowsGroupedByClass();
            if (!$workflowName = $workflowsByClass[$class][0] ?? null) {
                continue;
            }
            foreach ($markings as $idx => $marking) {
                $workflow = $this->workflowHelperService->getWorkflow($class, $workflowName);
                $color = $workflow->getMetadataStore()->getMetadata('bgColor', $marking);
                if (!$color) {
                    // palette? default from name, e.g. new, details, or the order
                    $color = match ($marking) {
                        'new' => 'lightYellow',
                        default => null
                    };
                    if (!$color) {
                        $color = $palette[$idx];

                    }
                }
                $colors[] = $color;
                //                dd($workflow, $workflowName, $color, $marking, $workflowName);

            }

            $chart = null;
            if ($this->chartBuilder) {
                $chart = $this->chartBuilder->createChart(Chart::TYPE_PIE);
                $chart->setData([
                    'labels' => $markings,
                    'datasets' => [[
                        'label' => $class,
                        'backgroundColor' => $colors,
                        'data' => array_values($markingCounts),
                    ]],
                ]);

                $chart->setOptions([
                    'responsive' => true,
                    'plugins' => [
                        'legend' => [
                            'position' => 'bottom',
                        ],
                    ],
                ]);
                if ($total)
                    $charts[$class] = [
                        'chart' => $chart,
                        'total' => $total,
                    ];
            }

        }

        return  [
            'chart' => $chart,
            'charts' => $charts,

            'counts' => $counts,
        ];
    }


    #[Route(path: '/workflow/transition/{workflowCode}/{entityIdentifier}/{transition}.{_format}', name: 'survos_workflow_transition', options: ['expose' => true])]
    public function _transition(Request                      $request,
                                WorkflowHelperService        $workflowHelperService,
                                EntityManagerInterface       $entityManager,
                                string|int                   $entityIdentifier, // for now, must be unique, we could somehow use rp though
                                string                       $workflowCode,
                                ?string                      $transition = null, // for the POST requests
                                #[MapQueryParameter] ?string $className = null,
                                #[MapQueryParameter] ?string $redirectRoute = null,
                                #[MapQueryParameter] array   $redirectParams = [],
                                string                       $_format = 'html',
    ): Response
    {
        /** @var MarkingInterface $entity */
        $entity = $this->entityManager->getRepository($className)->find($entityIdentifier);
        assert($entity, "$entityIdentifier not found in $className");

        $stateMachine = $workflowHelperService->getWorkflowByCode($workflowCode);
//        dd($stateMachine, $entity);

        //        $repo = $this->entityManager->getRepository($entity::class);
        if ($transition === '_hard_reset') {
            $entity->setMarking($stateMachine->getDefinition()->getInitialPlaces()[0]);
        } else {
            if ($stateMachine->can($entity, $transition)) {
                $stateMachine->apply($entity, $transition);
            } else {
                foreach ($stateMachine->buildTransitionBlockerList($entity, $transition) as $block) {
                    $this->addFlash('warning', $block->getMessage());
                }
            }
        }
        $entityManager->flush();
        return $this->redirectToRoute($redirectRoute, $redirectParams);
        return $this->jsonResponse($entity, $request, $_format);
    }

    #[Route("/workflow/{flowCode}", name: "survos_workflow")]
    public function workflow(Request $request,
                                     $flowCode, $entityClass = null): Response
    {
        // @todo: handle empty flowcode, needs to look up by class

        $workflow = $this->workflowHelperService->getWorkflowByCode($flowCode);
        $json = $this->serializer->serialize($workflow->getDefinition(), 'json');
//        dd($json, $workflow, json_encode($workflow->getDefinition()));
        $classes = $this->workflowHelperService->getSupports($flowCode);

//        dd($workflow, $classes);
        $entity = $entityClass ? (new $entityClass) : null;


        $params = [
            'flowName' => $flowCode,
            'flowCode' => $flowCode,
            'definition' => $workflow->getDefinition(),
            'classes' => $classes,
            'entity' => $entity,
        ];

        // need to get the marking store and set it properly.  This assumes we're using a live entity though.
        if ($entity) {
            if ($from = $request->get('states')) {
                $marking = $workflow->getMarking($entity);
                $markingStore = $workflow->getMarkingStore();
                // unset the current state
                foreach ($marking->getPlaces() as $place) {
                    $marking->unmark($place);
                }

                $place = json_decode($from);
                $marking->mark($place);
                $markingStore->setMarking($entity, $marking);

                $entity->setMarking($place);
                /*
                dump($marking);
                */
            }
        }

        if ($transitionName = $request->get('transitionName')) {
            $workflow->apply($entity, $transitionName);
        }

        $dumper = $this->workflowHelperService->workflowDiagramDigraph($entity, $flowCode, 'TB');

        // group by class
        return $this->render('@SurvosWorkflow/d3-workflow.html.twig', $params + [
                'digraph' => $dumper,
                // 'workflows' => $workflows['workflow']['workflows'],
            ]);
    }

}
