<?php

namespace Survos\WorkflowBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Survos\CoreBundle\Traits\QueryBuilderHelperInterface;
use Survos\CoreBundle\Service\SurvosUtils;
use Survos\WorkflowBundle\Message\TransitionMessage;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Process\Process;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Workflow\Attribute\AsCompletedListener;
use Symfony\Component\Workflow\Dumper\GraphvizDumper;
use Symfony\Component\Workflow\Dumper\StateMachineGraphvizDumper;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\SupportStrategy\InstanceOfSupportStrategy;
use Symfony\Component\Workflow\Workflow;
use Symfony\Component\Workflow\WorkflowInterface;
use Zenstruck\Messenger\Monitor\Stamp\DescriptionStamp;
use Zenstruck\Messenger\Monitor\Stamp\TagStamp;

/**
 * Global Workflow Listeners
 *
 * @author Tac Tacelosky <tacman@gmail.com>
 */
class WorkflowListener
{
    public function __construct(
        /** @var WorkflowInterface[] */
        #[AutowireLocator('workflow.state_machine')] private ServiceLocator $workflows,
        private WorkflowHelperService $workflowHelperService,
        private PropertyAccessorInterface $propertyAccessor,
        private MessageBusInterface $messageBus,
        private ?LoggerInterface                                            $logger = null,
    )
    {
    }

    private function getWorkflowsFromTaggedIterator(): iterable
    {
        return $this->workflows;
    }

    #[AsCompletedListener]
    public function onCompleted(CompletedEvent $event): void
    {
        $transition = $event->getTransition();
        $workflow = $this->workflowHelperService->getWorkflow($event->getSubject(), $event->getWorkflowName());
//        $workflow = $event->getWorkflow();
        foreach ($event->getMetadata('next', $transition)??[] as $nextTransition) {
            $object = $event->getSubject();
            if ($workflow->can($object, $nextTransition))
            {
                // we need the next transport of the _next_ transition
//                $nextTransport = $event->getMetadata('transport', $nextTransition);
                $transitionMeta = $this->workflowHelperService->getTransitionMetadata($nextTransition, $workflow);
                $nextTransport = $transitionMeta['transport']??null;
                $nextTransport ??= 'async';
                $stamps = [];
                if (class_exists(TagStamp::class)) {
                    $stamps[] = new TagStamp($nextTransition);
                }

                if ($nextTransport) {
                    $stamps[] = new TransportNamesStamp($nextTransport);
                }
                // always?
                // add getId() if id isn't the pk
                $id = $this->propertyAccessor->getValue($object, 'id');
                    $msg = new TransitionMessage(
                        $id,
                        $object::class,
                        $nextTransition,
                        $workflow->getName()
                    );
                    if (class_exists(DescriptionStamp::class)) {
                        $stamps[] = new DescriptionStamp(sprintf("Next/%s-%s @%s: %s",
                        new \ReflectionClass($event->getSubject())->getShortName(),
                        $id,
                        $event->getSubject()->getMarking(),
                        $nextTransition
                        ));
                    }
                    $env = $this->messageBus->dispatch($msg, $stamps);
//                } else {
//                    // we don't get the log
//                    $workflow->apply($event->getSubject(), $nextTransition);
//                    break; // stop dispatching after first match
//                }

                // getId()??  getKey()?  so that async messages have an id
//                dd($nextTransition, $nextTransport, $stamps, $msg);
//                dd(msg: $msg, env: $env, nextTransport: $nextTransport, nextTransition: $nextTransition);
            } else {
                $this->logger->warning("Cannot transition " . $object::class . "  to $nextTransition from " . $object->getMarking());
            }
        }
//        dd($transition, $event->getWorkflowName());


    }

}
