<?php

namespace Survos\WorkflowBundle\Message;

class TransitionMessage
{
    public function __construct(
        private int|string $id,
        private string $className,
        private string $transitionName,
        private string $workflow,
        private array $context=[], // to pass around tags and other extra properties
    ) {
    }

    public function getContext(): array
    {
        return $this->context;
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
