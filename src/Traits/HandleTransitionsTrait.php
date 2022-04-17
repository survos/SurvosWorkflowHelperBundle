<?php

namespace Survos\WorkflowBundle\Traits;

use Survos\BaseBundle\Entity\SurvosBaseEntity;
use Symfony\Component\Workflow\WorkflowInterface;

trait HandleTransitionsTrait
{
    public function handleTransitionButtons(WorkflowInterface $workflow, $transition, MarkingInterface $entity)
    {
        assert($workflow->can($entity, $transition), sprintf("%s cannot apply transition %s from %s", $entity, $transition, $entity->getMarking()));
        $workflow->apply($entity, $transition);
    }


}
