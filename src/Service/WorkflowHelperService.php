<?php

namespace Survos\WorkflowBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Survos\BaseBundle\Traits\QueryBuilderHelperInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Workflow\Dumper\GraphvizDumper;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Workflow;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Workflow\Dumper\StateMachineGraphvizDumper;


/**
 * Generate Workflow Diagrams
 *
 * @author Tac Tacelosky <tacman@gmail.com>
*/
class WorkflowHelperService
{
    private $dumper;
    private $direction;

    public function __construct(string $direction, private EntityManagerInterface $em, private Registry $workflowRegistry)
    {
        $this->direction = $direction;
        $this->dumper = new SurvosStateMachineGraphVizDumper();
    }

    /**
     * @deprecated
     * @return Registry
     */
    public function getRegistry() {
        return $this->workflowRegistry;
    }

    // @idea: pass in the repository to make the counts call.
    public function getMarkingData(WorkflowInterface $workflow, string $class, array $counts=null): array
    {
        $repo = $this->em->getRepository($class);
        if (!$repo instanceof QueryBuilderHelperInterface) {
            throw new \Exception($repo->getClassName() . " should implement QueryBuilderHelperInterface ");
        }
        $counts = $repo->findBygetCountsByField('marking'); //
        return array_map(fn  ($marking) =>
            array_merge(['marking' => $marking, 'count' => $counts[$marking] ?? null],  $workflow->getMetadataStore()->getPlaceMetadata($marking))
            , $workflow->getDefinition()->getPlaces());
    }


    /**
     * @param $subject
     * @param $workflowName
     * @param string $direction LR or TB
     * @return string
     */
    public function workflowDiagramDigraph($subject, $workflowName, $direction=null)
    {

        if ($direction) {
            $this->direction = $direction;
        }

        /** @var WorkflowInterface $workflow */
        try {
            $workflow = $this->workflowRegistry->get($subject, $workflowName);
        } catch (\Exception $e) {
            return $e->getMessage(); // null;
        }
        $definition = $workflow->getDefinition();
        $workflowPlaces = $workflow->getDefinition()->getPlaces();

        $entityPlaces = array_keys($workflow->getMarkingStore()->getMarking($subject)->getPlaces());
        $marking = $workflow->getMarkingStore()->getMarking($subject);

        // unset anything previously set
        array_map(function ($place) use ($marking) {
            if ($marking->has($place)) {
                $marking->unmark($place);
            }
        }, $workflowPlaces);

        // set it to the subject markings
        array_map(function ($place) use ($marking) {
            $marking->mark($place);
        },
            $entityPlaces);

        $dot = $this->dumper->dump($definition, $marking, [
            //graphviz docs http://www.graphviz.org/doc/info/attrs.html
            'graph' => [
                'ratio' => 'compress',
                'width' => 0.5,
                'rankdir' => null, // $this->direction,
                'ranksep' => 0.2],
            'node' => [
                'width' => 0.5,
                'shape' => 'ellipse'
            ],
            'edge' => [
                'shape' => 'box',
                'arrowsize' => '0.5'
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

    /** @return Workflow[] */
    public function getWorkflowsByCode($code = null)
    {
        $workflowService = $this->workflowRegistry;

        $reflectionProperty = new \ReflectionProperty(get_class($workflowService), 'workflows');
        $reflectionProperty->setAccessible(true);
        $workflowBlobs = $reflectionProperty->getValue($workflowService);
        $workflowsByCode = [];

        foreach ($workflowBlobs as $workflowBlob) {
            /** @var StateMachine  $x */
            $x = $workflowBlob[0];

            $class = $workflowBlob[1]->getClassName();
            $entity = new $class;
            $flowCode = $x->getName();
            /** @var Workflow $workflow */

            $workflow = $workflowService->get($entity, $flowCode);
            // $property = $workflow->getMarkingStore()->getProperty();

            // $entity->setMarking($workflow->getDefinition()->getInitialPlace());
            // dump($entity->getMarking(), $flowCode);


            /*
            $marking = $workflow->getMarkingStore()->getMarking($entity);
            $places = $marking->getPlaces();
            // dump( $marking, $places); // die();

            // $entity->setMarking($workflow->getDefinition()->getInitialPlace());


            $marking = $workflow->getMarking($entity);
            $places = $marking->getPlaces();
            dump($workflow->getMarkingStore());
            dd($workflow->getMarkingStore()->getProperty(), __METHOD__);
            dd($property, __METHOD__);
                */
            $places = $workflow->getDefinition()->getPlaces();
            $property  = $workflow->getDefinition();

            $workflowsByCode[$flowCode] =
                [
                    'initialPlace' => $places,
                    'workflow' => $workflow,
                    'class' => $class,
                    'entity' => $entity,
                    'definition' => $x->getDefinition()
                ];
        }
        return $code ? $workflowsByCode[$code] : $workflowsByCode;
    }

}
