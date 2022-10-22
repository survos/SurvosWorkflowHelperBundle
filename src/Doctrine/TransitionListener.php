<?php
declare(strict_types=1);

namespace Survos\WorkflowBundle\Doctrine;

use App\Entity\Article;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use Survos\WorkflowBundle\Traits\MarkingInterface;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Transition;

class TransitionListener
{
    public function __construct(private Registry $registry, private LoggerInterface $logger)
    {
    }

    public function postLoad(LifecycleEventArgs $args)
    {
        $enabledTransitions = [];
        $entity = $args->getObject(); //here you have access to your entity
        if ($entity instanceof MarkingInterface) {
//        if (method_exists($entity, 'setEnabledTransitions')) {
            // skip the ones that being with _, those are system-only
            $workflow = $this->registry->get($entity);
            try {
                $enabledTransitions  = array_values(array_filter(
                    $workflow->getEnabledTransitions($entity),
                    fn(Transition $transition) => substr($transition->getName(), 0, 1) <> '_'));
            } catch (\Exception $exception) {
                $this->logger->error($exception->getMessage(), [$entity->getMarking()]);
            }

            // this is so they can be serialized.
            $enabledTransitionCodes = array_map(fn(Transition $transition) => $transition->getName(), $enabledTransitions);
            $entity->setEnabledTransitions($enabledTransitionCodes);
            return;

            // get all the transitions, even the _ ones?
            foreach ($workflow->getDefinition()->getTransitions() as $transition) {
                $meta = $workflow->getMetadataStore()->getTransitionMetadata($transition);
                if ( (!empty($meta['label']) && substr($meta['label'], 0, 1) <> '*')) {
                    array_push($enabledTransitions, $transition->getName());
                }
            }
            $entity->setEnabledTransitions($enabledTransitions);
        }
    }

}
