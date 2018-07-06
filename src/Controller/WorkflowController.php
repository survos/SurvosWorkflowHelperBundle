<?php

namespace Survos\WorkflowBundle\Controller;

use App\Entity\License;
use App\Entity\Loan;
use Survos\WorkflowBundle\Service\WorkflowHelperService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Workflow;

class WorkflowController extends Controller
{

    protected $workflowRegistry;

    public function __construct(WorkflowHelperService $helper)
    {
        // $helper = $this->container->get('survos_workflow_bundle.workflow_helper'); // hmm, doesn't seem right.
        $this->helper = $helper;
        $this->workflowRegistry = $helper->getRegistry(); // hmm
        // $this->workflowRegistry = $this->get('workflow.registry'); // $helper->getRegistry();
    }



    /**
     * @Route("/top/workflow/{flowName}", name="survos_workflow")
     * @Route("/workflows", name="survos_workflows")
     */
    public function workflowAction(Request $request, $flowName = '', $entity = null)
    {
        $workflowService = $this->workflowRegistry; // $this->container->get('state_machine.service.workflow');
        // $workflowService = $this->container->get('workflow);

        $classes =  [];

        if ($workflowName = $request->get('xflowName')) {

        } else {

            if ($class = $request->get('class') ) {
            $classes = [$class];
        } else
            $classes = $this->container->getParameter('survos_workflow.entities');
        }

        foreach ($classes as $class) {
            if ($id = $request->get('id')) {
                $entity = $this->get('doctrine')->getRepository($class)->find($id);
            } else {
                $entity = new $class;
            }

            foreach ($workflowService->all($entity) as $wf) {
                $workflows[$class] = $wf;
                $flowCode = $wf->getName();
                $entities[$wf->getName()] = [
                    'initialPlace' => $wf->getDefinition()->getInitialPlace(),
                    'class' => $class,
                    'workflow' => $wf,
                    'object' => $entity
                ];
            }
        }

        // $twig = $this->container->get('twig');
        /*
        $workflowRegistry = $this->container->get('workflow.registry');
        $reflectionProperty = new \ReflectionProperty(get_class($workflowRegistry), 'workflows');
        $reflectionProperty->setAccessible(true);
        $workflows = $reflectionProperty->getValue($workflowRegistry);
        */
        $classes = [];
        $definitions = [];
        $classMap = [];
        /** @var Workflow $workflow */
        foreach ($workflows as $class => $workflow) { // list($workflow)) {
            $flowCode = $workflow->getName();
            // $class = $workflowService->getSupportedClass($workflow->getName());
            $c = new \ReflectionClass($class);
            $shortName = $c->getShortName();
            $classMap[$shortName] = $class;
            // $flowCode = $workflow->getName();
            $classes[$shortName][$flowCode] = $workflow;
            $definitions[$flowCode] = $workflow->getDefinition();
            if ($flowName == $flowCode) {
                $flowClass = $class;
            }
        }

        // $workflowFile = $this->getParameter('kernel.root_dir').'/config/workflow.yml';
        // $workflows = Yaml::parse(file_get_contents($workflowFile));
        $places = [];
        if ($flowName) {
            /** @var Workflow $workflow */
            $workflow = $this->get('state_machine.' . $flowName);
            /* this should be moved to the individual element Controller, or have the class name in the request object */
            if ($id = $request->get('id')) {
                if (empty($flowClass)) {
                    $flowClass = $request->get('class');
                }
                if (!$entity = $this->getRepo($flowClass)->find($id)) {
                    throw new \Exception("No $flowClass with id $id");
                }
            }
            else {
                if (!$entity) {
                    $class = $request->get('class');
                    $entity = $class ? new $class: null;
                }
            }
            if ($state = $request->get('state')) {
                $entity->setMarking($state);
            }
            if (($transition = $request->get('transition')) && $this->isGranted('ROLE_OWNER')) {
                if ($transition == 'hard_reset') {
                    $entity->setMarking(null);
                } else {
                    try {
                        $this->get('state_machine.' . $flowName)
                            ->apply($entity, $transition);
                        $this->addFlash('notice', "$transition applied");
                    } catch (\Exception $e) {
                        $this->addFlash('error', $transition . " " . $e->getMessage());
                    }
                }
                if ($id) {
                    $this->getEntityManager()->flush($entity);
                    if ($request->get('return_to_referrer', true)) {
                        return $this->redirectToReferer($request);
                    }
                }
            }
            $params = [
                'workflow' => $workflow,
                'definitions' => $definitions,
                'classMap' => $classMap,
                // 'definition' => $definition,
                'places' => $places,
                'class' => $class,
                'entity' => $entity,
                'flowName' => $flowName,
            ];
        } else {
            $params = [];
        }
        // group by class
        return $this->render('@SurvosWorkflow/index.html.twig', $params + [
                'classes' => $classes,
                'classMap' => $classMap,
                'definitions' => $definitions,
                'workflows' => $workflows,
                'entities' => $entities,
                // 'workflows' => $workflows['workflow']['workflows'],
            ]);
    }
    /**
     * @Route("/workflow/download/{flowName}.svg", name="project_workflow_svg")
     */
    public function downloadWorkflowAction(Request $request, $flowName)
    {
        $workflowService = $this->container->get('state_machine.service.workflow');
        $class = $workflowService->getSupportedClass($flowName);
        if (!$class) {
            throw new BadRequestHttpException(sprintf('Workflow "%s" is not supported. Maybe wrong name?', $flowName));
        }
        $entity = new $class;
        $direction = $request->get('direction', 'LR');
        $svg = $this->get('posse.twig.survey_extension')->workflowDiagram($entity, $flowName, $direction);
        return new Response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }
}
