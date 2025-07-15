<?php

namespace Survos\WorkflowBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class Transition
{
    public function __construct(
        public array|string $from,
        public array|string $to,
        public ?string $info=null,
        public ?string $guard=null,
        public ?array $metadata=[],
        
    ) {
        if ($guard) {
            $this->metadata['guard'] = $guard;
        }
        if ($this->info) {
            $this->metadata['description'] = $this->info;
        }

    }

    public function getFrom(): string|array
    {
        return $this->from;
    }
}
