<?php

namespace Survos\WorkflowBundle\Controller;

use Survos\WorkflowBundle\Service\WorkflowHelperService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Workflow;

class WorkflowController extends AbstractController
{

    protected $workflowRegistry;
    protected $helper;

    public function __construct(WorkflowHelperService $helper, Registry $registry)
    {
        // $helper = $this->container->get('survos_workflow_bundle.workflow_helper'); // hmm, doesn't seem right.
        $this->helper = $helper;
        $this->workflowRegistry = $registry; // $helper->getRegistry(); // hmm
        // $this->workflowRegistry = $this->get('workflow.registry'); // $helper->getRegistry();
    }

    /**
     * @Route("/", name="survos_workflows")
     */
    public function index(Request $request)
    {
        $workflowsByCode = $this->helper->getWorkflowsByCode();
        return $this->render("@SurvosWorkflow/index.html.twig", [
            'workflowsByCode' => $workflowsByCode
        ]);
    }


    /**
     * @Route("/workflow/{flowCode}", name="survos_workflow")
     */
    public function workflowAction(Request $request, $flowCode=null, $entity = null)
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

        // need to get the marking store and set it properly!

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

        if ($transitionName = $request->get('transitionName')) {
            $workflow->apply($entity, $transitionName);
        }

        $dumper = $this->helper->workflowDiagramDigraph($entity, $flowCode);

        // group by class
        return $this->render('@SurvosWorkflow/d3-workflow.html.twig', $params + [
                'digraph' => $dumper,
                // 'workflows' => $workflows['workflow']['workflows'],
            ]);

    }

    private function oldWay(Request $request)
    {
        $workflowService = $this->workflowRegistry; // $this->container->get('state_machine.service.workflow');
        // $workflowService = $this->container->get('workflow);

        $classes =  [];
        $workflows = [];
        $entities = [];

        $transitionName = $request->get('transitionName');

        if ($workflowName = $request->get('flowCode')) {

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
                // overwrite marking if it exists in the request
                if ($marking = $request->get('marking'))
                {
                    $entity->setMarking($marking);
                }

            }

            $transitions = $transitionName
                ? $workflowService->get($entity, $transitionName)
                : $workflowService->all($entity);


            foreach ($workflowService->all($entity) as $wf) {
                $workflows[$class] = $wf;
                $flowCode = $wf->getName();
                $entities[$wf->getName()] = [
                    'initialPlace' => $entity->getMarking(), // || $wf->getDefinition()->getInitialPlace(),
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
            if ($flowCode == $flowCode) {
                $flowClass = $class;
            }
        }

        // $workflowFile = $this->getParameter('kernel.root_dir').'/config/workflow.yml';
        // $workflows = Yaml::parse(file_get_contents($workflowFile));
        $places = [];
        if ($flowCode) {
            /** @var Workflow $workflow */
            $workflow = $this->get('state_machine.' . $flowCode);
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
                        $this->get('state_machine.' . $flowCode)
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
                'flowName' => $flowCode,
            ];
        } else {
            $params = [];
        }
        // group by class
        return $this->render('@SurvosWorkflow/workflow.html.twig', $params + [
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
