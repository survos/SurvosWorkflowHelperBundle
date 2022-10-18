<?php

namespace Survos\WorkflowHelperBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
class Transition
{
    public function __construct(
        public array|string $from,
        public array|string $to,
        public ?string $guard=null,
        public ?array $metadata=null
    ) {

    }

    public function getFrom(): string|array
    {
        return $this->from;
    }
}
