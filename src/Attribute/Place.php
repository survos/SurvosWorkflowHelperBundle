<?php

namespace Survos\WorkflowBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class Place
{
    public function __construct(
        public bool $initial=false,
        public array $metadata=[],
        public ?string $info=null, // fits inside node
        public ?string $description=null, // long description, xlabel is outside of node
        public ?string $bgColor=null, // graph color
        public ?array $next=null, // only if initial:true
  ) {
        if ($this->info) {
            $this->metadata['info'] = $this->info;
        }
        if ($this->description) {
            $this->metadata['description'] = $this->description;
        }
        if ($this->bgColor) {
            $this->metadata['bgColor'] = $this->bgColor;
        }
        if ($this->next) {
            $this->metadata['next'] = $this->next; // an array.  Maybe make it a string for existing tools
        }

    }

    public function getIsInitial(): bool
    {
        return $this->initial;
    }
}
