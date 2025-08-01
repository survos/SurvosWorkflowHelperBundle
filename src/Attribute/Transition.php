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
        public ?string $description=null,
        public ?string $guard=null,
        public ?array $metadata=[],
        public ?string $transport=null,
        public ?array $next=[],

    ) {
        if ($guard) {
            $this->metadata['guard'] = $guard;
        }
        if ($this->info) {
            $this->metadata['info'] = $this->info;
        }
        if ($this->description) {
            $this->metadata['description'] = $this->description;
        }
        if ($this->transport) {
            $this->metadata['transport'] = $this->transport;
        }
        if ($this->next) {
            $this->metadata['next'] = $this->next;
        }

    }

    public function getFrom(): string|array
    {
        return $this->from;
    }
}
