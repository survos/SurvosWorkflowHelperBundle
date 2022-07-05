<?php

namespace Survos\WorkflowBundle\Traits;

use Survos\BaseBundle\Entity\SurvosBaseEntity;
use Survos\WorkflowBundle\Message\AsyncTransitionMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;
use function Symfony\Component\String\u;

trait HandleTransitionsTrait
{
    public function handleTransitionButtons(
        WorkflowInterface $workflow,
        string $transition,
        MarkingInterface $entity,
        ?MessageBusInterface $bus=null,
    ): ?string
    {
        $flashMessage = null;
        // use message handler instead.
        $suffix = '_async';
        if (u($transition)->endsWith($suffix) && $bus) {
            $tName = trim($transition, $suffix);

//            $transitions  = $workflow->getDefinition()->getTransitions();
//            $t = current(array_filter($transitions , fn(Transition $transition) => $transition->getName() === $tName));

//            $messageClass = $workflow
//                    ->getMetadataStore()
//                    ->getTransitionMetadata($t)['messageClass'];
//            ;
//            $message = new $messageClass($entity->getId());
            $message = (new AsyncTransitionMessage($entity->getId(), $entity::class, $tName));
            $envelope = $bus->dispatch($message);
//            dd($envelope, $message);
            $flashMessage = $message::class . ' has been dispatched, ' . $tName . " will happen in the message handler.";
        } else {
            assert($workflow->can($entity, $transition), sprintf("%s cannot apply transition %s from %s", $entity, $transition, $entity->getMarking()));
            $workflow->apply($entity, $transition);
            $flashMessage = $transition . ' has been applied.';
        }
        return $flashMessage;
    }

    public function dispatchMessage(WorkflowInterface $workflow, $transition, MarkingInterface $entity)
    {
        assert($workflow->can($entity, $transition), sprintf("%s cannot apply transition %s from %s", $entity, $transition, $entity->getMarking()));
        $workflow->apply($entity, $transition);
    }

}
