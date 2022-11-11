<?php

namespace Survos\WorkflowBundle\Twig;

use Survos\WorkflowBundle\Service\WorkflowHelperService;
use Symfony\Component\Workflow\Transition;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class WorkflowExtension extends AbstractExtension
{
    private $workflowHelper;

    public function __construct(WorkflowHelperService $workflowHelper)
    {
        $this->workflowHelper = $workflowHelper;
    }

    public function getFilters(): array
    {
        return [
            // new TwigFilter('filter_name', [$this, 'doSomething'], ['is_safe' => ['html']]),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('workflow_diagram', [$this, 'workflowDiagram'], [
                'is_safe' => ['html'],
            ]),
            new TwigFunction('workflow_digraph', [$this, 'workflowDigraph'], [
                'is_safe' => ['html'],
            ]),
            new TwigFunction('entity_short_class', [$this, 'getShortClass']),
            new TwigFunction('survos_workflow_metadata', $this->getWorkflowMetadata(...)),
            new TwigFunction('entity_class', [$this, 'getClass']),

        ];
    }

    public function getClass($object)
    {
        // return $object::class;
        return (new \ReflectionClass($object))->getName();
    }

    public function getShortClass($object)
    {
        return (new \ReflectionClass($object))->getShortName();
    }

    /**
     * @param $subject
     * @param $workflowName
     * @param string $direction LR or TB
     * @return string
     */
    public function workflowDiagram($subject=null, $workflowName=null, $direction = 'LR')
    {
        assert($subject || $workflowName, "must pass either a subject or workflowName");
        return $this->workflowHelper->workflowDiagram($subject, $workflowName, $direction);
    }

    /**
     * @param $subject
     * @param $workflowName
     * @param string $direction LR or TB
     * @return string
     */
    public function workflowDigraph($subject, $workflowName, $direction = 'LR')
    {
        return $this->workflowHelper->workflowDiagramDigraph($subject, $workflowName, $direction);
    }

    /**
     * Returns the metadata for a specific workflow.
     *
     * @param string|Transition|null $metadataSubject Use null to get workflow metadata
     *                                                Use a string (the place name) to get place metadata
     *                                                Use a Transition instance to get transition metadata
     */
    public function getWorkflowMetadata(string $name, string $key, string|Transition $metadataSubject = null)
    {
        return $this->workflowHelper->getWorkflowByCode($name)
            ->getMetadataStore()
            ->getMetadata($key, $metadataSubject)
            ;
    }



}
