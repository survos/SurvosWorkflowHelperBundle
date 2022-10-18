<?php

namespace Survos\WorkflowHelperBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Workflow
{
    public function __construct(
        public ?string $prefix=null, // place prefix
        public string $type='state_machine' // or workflow
    ) {
    }

    public function getPlacePrefix(): ?string
    {
        return $this->prefix;
    }
}
