<?php

namespace Survos\WorkflowBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class Place
{
    public function __construct(
        public bool $initial=false,
        public array $metadata=[]
    ) {

    }

    public function getIsInitial(): bool
    {
        return $this->initial;
    }
}
