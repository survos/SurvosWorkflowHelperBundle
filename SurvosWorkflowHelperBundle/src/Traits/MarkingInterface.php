<?php

namespace Survos\WorkflowBundle\Traits;

interface MarkingInterface
{
    public function getMarking(): ?string;

    public function setMarking(?string $marking, $context = []): self;

    public function setEnabledTransitions(array $enabledTransitions): self;

    public function getEnabledTransitions(): ?array;

    public function getEnabledTransitionCodes(): array;

    public static function getConstants(?string $prefix = null);

    public static function getFlowCodes(?string $prefix = null);

    public function getFlowCode(): string;
}
