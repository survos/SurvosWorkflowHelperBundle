<?php

namespace Survos\WorkflowBundle\MessageHandler;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Survos\WorkflowBundle\Message\AsyncTransitionMessage;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\Registry;

final class AsyncTransitionMessageHandler
{
    public function __construct(
        private Registry $workflowRegistry,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

//    public static function getHandledMessages(): iterable
//    {
//        // handle this message on __invoke
//        yield AsyncTransitionMessage::class;
//    }
//
    #[AsMessageHandler()]
    public function __invoke(AsyncTransitionMessage $message)
    {
        $entity = $this->entityManager->find($message->className, $message->id);
        $transitionName = $message->getTransitionName();
        //        dd($transitionName, __FILE__);
        $workflow = $this->workflowRegistry->get($entity);
        if ($workflow->can($entity, $transitionName)) {
            $workflow->apply($entity, $transitionName);
            $this->logger->info("Applied $transitionName to $message->className " . $message->id);
        } else {
            $this->logger->warning("Unable to apply $transitionName to $message->className " . $message->id);
        }
        $this->entityManager->flush();
    }
}
