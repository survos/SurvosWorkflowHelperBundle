<?php


namespace Survos\WorkflowBundle\Traits;

use Gedmo\Loggable\Entity\LogEntry;

trait HistoryTrait
{
    public function getHistory($entity)
    {
        $repo = $this->em->getRepository(LogEntry::class);
        $logs = $repo->getLogEntries($entity);
        dd($logs, __METHOD__);

    }

}
