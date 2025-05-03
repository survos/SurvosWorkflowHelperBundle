<?php


namespace Survos\WorkflowBundle\Traits;

use Doctrine\ORM\EntityManagerInterface;
use Survos\CoreBundle\Traits\JsonResponseTrait;
use Survos\WorkflowBundle\Service\WorkflowHelperService;
use Survos\WorkflowBundle\Traits\MarkingInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\WorkflowInterface;

trait WorkflowHelperTrait
{
    use JsonResponseTrait;

    public function _transition(Request                $request,
                                MarkingInterface       $entity,
                                WorkflowHelperService  $workflowHelperService,
                                WorkflowInterface      $stateMachine,
                                EntityManagerInterface $entityManager,
                                ?string                $transition = null, // for the POST requests
                                string                 $_format = 'json'): Response
    {
        //        $repo = $this->entityManager->getRepository($entity::class);
        if ($transition === '_hard_reset') {
            $entity->setMarking($stateMachine->getDefinition()->getInitialPlaces()[0]);
        } else {
            dd($entity, $transition);
            $stateMachine->apply($entity, $transition);
        }
        $entityManager->flush();
        return $this->jsonResponse($entity, $request, $_format);
    }
}
