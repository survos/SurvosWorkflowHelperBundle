<?php

namespace Survos\WorkflowBundle\Message;

final class AsyncTransitionMessage
{

    public function __construct(public int $id, public string $className, private string $transitionName)
    {
    }

    public function getTransitionName(): string
    {
        return $this->transitionName;
    }
}
