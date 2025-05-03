<?php

namespace Survos\WorkflowBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Survos\WorkflowBundle\Service\WorkflowHelperService;
use Survos\WorkflowBundle\Traits\MarkingInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Workflow\WorkflowInterface;

class WorkflowController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        protected WorkflowHelperService $helper,

    ) // , private Registry $registry)
    {
//        foreach ($this->tagged->getIterator() as $workflow) {
//            dd($workflow);
//        }
//        dd($this->tagged);
        // $helper = $this->container->get('survos_workflow_bundle.workflow_helper'); // hmm, doesn't seem right.
        $this->helper = $helper;
        // $this->workflowRegistry = $this->get('workflow.registry'); // $helper->getRegistry();
    }

    #[Route("/", name: "survos_workflows")]
    public function workflows(Request $request): Response
    {
        $workflowsGroupedByCode = $this->helper->getWorkflowsIndexedByName();
        $workflowsGroupedByClass = $this->helper->getWorkflowsGroupedByClass();
        return $this->render("@SurvosWorkflow/index.html.twig", [
            'configs' => $this->helper->getWorkflowConfiguration(),
            'workflowsGroupedByClass' => $workflowsGroupedByClass,
            'workflowsByCode' => $workflowsGroupedByCode,
        ]);
    }

    #[Route(path: '/workflow/transition/{workflowCode}/{entityIdentifier}/{transition}.{_format}', name: 'survos_workflow_transition', options: ['expose' => true])]
    public function _transition(Request                $request,
                                WorkflowHelperService  $workflowHelperService,
                                EntityManagerInterface $entityManager,
                                string|int $entityIdentifier, // for now, must be unique, we could somehow use rp though
                                string                $workflowCode,
                                ?string                $transition = null, // for the POST requests
                                #[MapQueryParameter] ?string $className=null,
                                #[MapQueryParameter] ?string $redirectRoute=null,
                                #[MapQueryParameter] array $redirectParams=[],
                                string                 $_format = 'html',
    ): Response
    {
        /** @var MarkingInterface $entity */
        $entity = $this->em->getRepository($className)->find($entityIdentifier);
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

        $workflow = $this->helper->getWorkflowByCode($flowCode);
        $json = $this->serializer->serialize($workflow->getDefinition(), 'json');
        dd($json, $workflow, json_encode($workflow->getDefinition()));
        $classes = $this->helper->getSupports($flowCode);

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

        $dumper = $this->helper->workflowDiagramDigraph($entity, $flowCode, 'TB');

        // group by class
        return $this->render('@SurvosWorkflow/d3-workflow.html.twig', $params + [
            'digraph' => $dumper,
            // 'workflows' => $workflows['workflow']['workflows'],
        ]);
    }

}
