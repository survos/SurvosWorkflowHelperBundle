<?php

namespace Survos\WorkflowBundle\Traits;

interface MarkingInterface
{
    public function getMarking(): ?string;
    public function setMarking(?string $marking, $context=[]): self;
    public function setEnabledTransitions(array $enabledTransitions): self;
    public function getEnabledTransitions(): ?array;
    public function getEnabledTransitionCodes(): array;
    static function getConstants(?string $prefix = null);
    static function getFlowCodes(?string $prefix = null);
    public function getFlowCode(): string;

    }
