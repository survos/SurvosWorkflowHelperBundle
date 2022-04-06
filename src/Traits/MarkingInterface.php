<?php

namespace Survos\WorkflowBundle\Traits;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Workflow\Transition;

interface MarkingInterface
{
    public function getMarking(): ?string;

}
