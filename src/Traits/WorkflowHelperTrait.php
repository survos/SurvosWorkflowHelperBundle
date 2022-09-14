<?php


namespace Survos\WorkflowBundle\Traits;

use Doctrine\ORM\EntityManagerInterface;
use Survos\CoreBundle\Traits\JsonResponseTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\Workflow\WorkflowInterface;
use Survos\WorkflowBundle\Traits\MarkingInterface;

trait WorkflowHelperTrait
{
    use JsonResponseTrait;

    protected function _transition(Request $request, MarkingInterface $entity, $transition, WorkflowInterface $stateMachine, EntityManagerInterface $entityManager, $class, $_format = 'json'): Response
    {
//        $repo = $this->entityManager->getRepository($entity::class);
        if ($transition === '_hard_reset') {
            $entity->setMarking($stateMachine->getDefinition()->getInitialPlaces()[0]);
        } else {
            $stateMachine->apply($entity, $transition);
        }
        $entityManager->flush();
        return $this->jsonResponse($entity, $request, $_format);
    }

}
