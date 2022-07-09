<?php

namespace Survos\WorkflowBundle\Controller;

use Survos\WorkflowBundle\Traits\MarkingInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

interface HandleTransitionsInterface
{
    public function handleTransitionButtons(
        WorkflowInterface $workflow,
        string $transition,
        MarkingInterface $entity,
        ?MessageBusInterface $bus=null,
    ): ?string;

    public function dispatchMessage(WorkflowInterface $workflow, $transition, MarkingInterface $entity);

}
