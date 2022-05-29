<?php

namespace Survos\WorkflowBundle\Controller;

use Survos\WorkflowBundle\Service\WorkflowHelperService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Workflow\Workflow;

class WorkflowController extends AbstractController
{

    protected $workflowRegistry;
    protected $helper;

    public function __construct(WorkflowHelperService $helper) // , private Registry $registry)
    {
        // $helper = $this->container->get('survos_workflow_bundle.workflow_helper'); // hmm, doesn't seem right.
        $this->helper = $helper;
        // $this->workflowRegistry = $this->get('workflow.registry'); // $helper->getRegistry();
    }

    /**
     * @Route("/", name="survos_workflows")
     */
    public function workflows(Request $request)
    {
        $workflowsGroupedByCode = $this->helper->getWorkflowsByCode();
        $workflowsGroupedByClass = $this->helper->getWorkflowsGroupedByClass();
        return $this->render("@SurvosWorkflow/index.html.twig", [
            'workflowsGroupedByClass' => $workflowsGroupedByClass,
            'workflowsByCode' => $workflowsGroupedByCode
        ]);
    }


    /**
     * @Route("/workflow/{flowCode}", name="survos_workflow")
     */
    public function workflow(Request $request, $flowCode=null, $entityClass=null): Response
    {

        // @todo: handle empty flowcode, needs to look up by class

        $wrapper = $this->helper->getWorkflowsByCode($flowCode);
        /** @var Workflow $workflow */
        $workflow = $wrapper['workflow'];
        $entity = $wrapper['entity'];

        $params = [
            'flowName' => $flowCode,
            'flowCode' => $flowCode,
            'definition' => $wrapper['definition'],
            'class' => $wrapper['class'],
            'entity' => $entity,
        ];

        // need to get the marking store and set it properly.  This assumes we're using a live entity though.
        if ($from = $request->get('states'))
        {

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

    /**
     * @ Route("/workflow/download/{flowName}.svg", name="project_workflow_svg")
     */
    public function OLD_downloadWorkflowAction(Request $request, $flowName)
    {
        $workflowService = $this->container->get('state_machine.service.workflow');
        $class = $workflowService->getSupportedClass($flowName);
        if (!$class) {
            throw new BadRequestHttpException(sprintf('Workflow "%s" is not supported. Maybe wrong name?', $flowName));
        }
        $entity = new $class;
        $direction = $request->get('direction', 'LR');
//        $svg = $this->get('posse.twig.survey_extension')->workflowDiagram($entity, $flowName, $direction);
//        return new Response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }
}
