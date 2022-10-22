<?php
declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\Article;
use App\Entity\GeoInterface;
use App\Entity\Link;
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

class PreFlushEventListener
{
    public function __construct()
    {
    }

    public function preFlush(PreFlushEventArgs $args): void
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        $resolver = (new OptionsResolver());
        foreach (Project::getAllRelatedClasses() as $class) {
            $resolver->setDefault($class, 0);
        }

        // if we were careful, we could simply add and delete, rather than recomputing...
        foreach ([1 => $uow->getScheduledEntityInsertions(), -1 => $uow->getScheduledEntityDeletions()] as $inc => $items) {

            foreach ($items as $entity) {
                foreach ($items as $entity) {
                    switch ($entity::class) {
                        case Article::class:
                            $entity->getMedia()->incrementArticleCount($inc); break;
                        case Link::class:
                            $entity->getPage()->incrementLinkCount($inc); break;
                    }
                }
            }
        }
    }
}
