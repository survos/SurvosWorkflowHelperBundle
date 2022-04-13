<?php

namespace Survos\WorkflowBundle\Traits;

use Survos\BaseBundle\Entity\SurvosBaseEntity;
use Symfony\Component\Workflow\WorkflowInterface;

trait HandleTransitionsTrait
{
    public function handleTransitionButtons(WorkflowInterface $workflow, $transition, SurvosBaseEntity $entity)
    {
        assert($workflow->can($entity, $transition));
        $workflow->apply($entity, $transition);
    }


}
