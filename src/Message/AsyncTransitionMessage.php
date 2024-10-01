<?php

namespace Survos\WorkflowBundle\Message;

final class AsyncTransitionMessage
{
    public function __construct(
        private int|string $id,
        private string $className,
        private string $transitionName,
        private string $workflow,
    ) {
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getWorkflow(): string
    {
        return $this->workflow;
    }

    public function getTransitionName(): string
    {
        return $this->transitionName;
    }
}
