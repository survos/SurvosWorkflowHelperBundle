<?php

namespace Survos\WorkflowBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class Place
{
    public function __construct(
        public bool $initial=false,
        public array $metadata=[],
        public ?string $info=null,
        public ?string $bgColor=null, // graph color
  ) {
        if ($this->info) {
            $this->metadata['description'] = $this->info;
        }
        if ($this->bgColor) {
            $this->metadata['bgColor'] = $this->bgColor;
        }

    }

    public function getIsInitial(): bool
    {
        return $this->initial;
    }
}
