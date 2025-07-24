<?php

namespace Survos\WorkflowBundle\Message;

use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
final class AsyncTransitionMessage
{
    public function __construct(
        private int|string $id,
        private string $className,
        private string $transitionName,
        private string $workflow,
        private array $context=[], // to pass around tags and other extra properties
    ) {
        assert(false, "use TransitionMessage instead of AsyncTransitionMessage");
    }

}
