<?php
declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\Article;
use App\Entity\Edition;
use App\Entity\Media;
use App\Entity\Project;
use App\Entity\Site;
use App\Entity\Story;
use App\Entity\Subscription;
use App\Entity\Tag;
use App\Entity\Category;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Symfony\Component\HttpKernel\Log\Logger;

class ProjectFilter extends SQLFilter
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * Gets the SQL query part to add to a query.
     *
     * @param ClassMetaData $targetEntity
     * @param string $targetTableAlias
     *
     * @return string The constraint SQL if there is available, empty string otherwise.
     */

    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias)
    {
        if (in_array($className = $targetEntity->getReflectionClass()->name, Project::RELATED_CLASSES))
        {
            $project_id = $this->getParameter('project_id');
            assert(!empty($project_id), "Missing project_id for " . $targetTableAlias . ' ' . $className);
            return sprintf('%s.project_id = %s', $targetTableAlias, $project_id);
            try {
            } catch (\Exception $e) {
                // logger?
            }
        }

        return '';

    }
}
