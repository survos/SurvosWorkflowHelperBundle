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
//        dd($transition, $event->getWorkflowName(),          ));
//        $workflow = $event->getWorkflow();
        foreach ($event->getMetadata('next', $transition)??[] as $nextTransition) {
            if ($workflow->can($event->getSubject(), $nextTransition)) {
                // we need the next transport of the _next_ transition
//                $nextTransport = $event->getMetadata('transport', $nextTransition);
                $transitionMeta = $this->workflowHelperService->getTransitionMetadata($nextTransition, $workflow);
                $nextTransport = $transitionMeta['transport']??null;

//                $nextTransport = $workflow    ->getMetadataStore()->getMetadata('transport', $nextTransition);
                $stamps = [];
                if (class_exists(TagStamp::class)) {
                    $stamps[] = new TagStamp($nextTransition);
                }

                if ($nextTransport) {
                    $stamps[] = new TransportNamesStamp($nextTransport);
                    $msg = new TransitionMessage(
                        $event->getSubject()->getId(),
                        $event->getSubject()::class,
                        $nextTransition,
                        $workflow->getName()
                    );
                    $env = $this->messageBus->dispatch($msg, $stamps);
                } else {
                    // we don't get the log
                    $workflow->apply($event->getSubject(), $nextTransition);
                }
                // getId()??  getKey()?  so that async messages have an id
//                dd($nextTransition, $nextTransport, $stamps, $msg);
//                dd(msg: $msg, env: $env, nextTransport: $nextTransport, nextTransition: $nextTransition);
                break; // stop dispatching after first match
            }
        }

    }

}
