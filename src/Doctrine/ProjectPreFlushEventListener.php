<?php
declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\GeoInterface;
use App\Entity\Project;
use App\Entity\ProjectInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Psr\Log\LoggerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Transition;

class ProjectPreFlushEventListener
{
    public function __construct(
    )
    {
    }

    public function preFlush(PreFlushEventArgs $args): void
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        $classes = [];
        $projectClassCounts = [];
        /** @var ProjectInterface $entity */

        $resolver = (new OptionsResolver());
        foreach (Project::getAllRelatedClasses() as $class) {
            $resolver->setDefault($class, 0);
        }
        $projects = [];


        // if we were careful, we could simply add and delete, rather than recomputing...
        foreach ([1 => $uow->getScheduledEntityInsertions(), -1 => $uow->getScheduledEntityDeletions()] as $inc => $items) {

            foreach ($items as $entity) {

                $relatedClass = $entity::class;
                if (!$entity instanceof ProjectInterface) {
                    continue;
                }
                $projectId = $entity->getProjectId();
                if (!array_key_exists($projectId, $projectClassCounts)) {
                    $projects[$projectId] = $entity->getProject(); // we we have it later.
                    $projectClassCounts[$projectId] = [];
                }
                if (!array_key_exists($relatedClass, $projectClassCounts[$projectId])) {
                    $projectClassCounts[$projectId][$relatedClass] = 0;
                }
                // now actually count the delta!
                $projectClassCounts[$projectId][$relatedClass] += $inc;
            }
        }

            //

            foreach ($projectClassCounts as $projectId => $relatedClasses) {
                $project = $projects[$projectId];
                $currentCounts = $project->getClassCounts();
                $newCounts = $projectClassCounts[$projectId];
                foreach ($relatedClasses as $relatedClass => $delta) {
                    if (!array_key_exists($relatedClass, $currentCounts)) {
                        $currentCounts[$relatedClass] = 0;
                    }
//                    dump($currentCounts, $relatedClass, $newCounts, $delta);
                    $currentCounts[$relatedClass] += $newCounts[$relatedClass]; // the inc/dec for this flush
                }
                $project->setClassCounts($currentCounts);
            }
    }
}
