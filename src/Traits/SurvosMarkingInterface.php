<?php

namespace Survos\WorkflowBundle\Traits;

interface SurvosMarkingInterface
{
    public function getMarking(): ?string;
    public function setMarking(?string $marking, $context=[]): self;


}
