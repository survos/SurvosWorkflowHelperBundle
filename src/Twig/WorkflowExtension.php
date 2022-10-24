<?php

namespace Survos\WorkflowBundle\Twig;

use Survos\WorkflowBundle\Service\WorkflowHelperService;
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
    public function workflowDiagram($subject, $workflowName, $direction = 'LR')
    {
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


}
