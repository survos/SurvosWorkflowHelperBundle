<?php

namespace Survos\WorkflowBundle\Traits;

use Doctrine\ORM\Mapping as ORM;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

use function Symfony\Component\String\u;

trait EasyMarkingTrait
{
    protected WorkflowInterface $workflow;
    protected function getPlaces(): array
    {
        return $this->workflow->getDefinition()->getPlaces();
    }

    protected function markingFilter(string $propertyName='marking')
    {
        return ChoiceFilter::new($propertyName)
            ->setChoices($this->getPlaces()
            );
    }

}
