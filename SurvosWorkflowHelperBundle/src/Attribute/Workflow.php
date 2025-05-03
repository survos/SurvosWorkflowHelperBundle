<?php

namespace Survos\WorkflowBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Workflow
{
    public function __construct(
        public ?string $prefix=null, // place prefix
        public string $type='state_machine', // or workflow,
        public array $supports=['stdClass'], // or empty?
        public ?string $name=null, // defaults to shortName
        public string|array|null $initial=null // array if type is workflow
    ) {
    }

    public function getPlacePrefix(): ?string
    {
        return $this->prefix;
    }
    
}
