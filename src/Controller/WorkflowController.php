<?php

namespace Survos\WorkflowBundle\Controller;

use Survos\WorkflowBundle\Service\WorkflowHelperService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class WorkflowController extends AbstractController
{
    public function __construct(
        protected WorkflowHelperService $helper) // , private Registry $registry)
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
    public function workflows(Request $request)
    {

        $workflowsGroupedByCode = $this->helper->getWorkflowsIndexedByName();
        $workflowsGroupedByClass = $this->helper->getWorkflowsGroupedByClass();
        return $this->render("@SurvosWorkflow/index.html.twig", [
            'configs' => $this->helper->getWorkflowConfiguration(),
            'workflowsGroupedByClass' => $workflowsGroupedByClass,
            'workflowsByCode' => $workflowsGroupedByCode,
        ]);
    }

    /**
     * @Route("/workflow/{flowCode}", name="survos_workflow")
     */
    public function workflow(Request $request, $flowCode = null, $entityClass = null): Response
    {
        // @todo: handle empty flowcode, needs to look up by class

        $workflow = $this->helper->getWorkflowByCode($flowCode);
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
