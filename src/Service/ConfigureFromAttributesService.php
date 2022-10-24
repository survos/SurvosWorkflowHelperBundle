<?php

namespace Survos\WorkflowBundle\Service;

use Survos\WorkflowBundle\Attribute\Place;
use Survos\WorkflowBundle\Attribute\Transition;
use Symfony\Config\FrameworkConfig;

class ConfigureFromAttributesService
{
    static public function configureFramework(string $workflowClass, FrameworkConfig $framework, array $supports)
    {
        $reflectionClass = new \ReflectionClass($workflowClass);
        foreach ($reflectionClass->getAttributes() as $attribute) {

//            dump($attribute->getArguments());
//            dd($attribute->newInstance());

        }

        // look in attribute first
        $workflow = $framework->workflows()->workflows($reflectionClass->getShortName())
            ->supports($supports);

        $constants = $reflectionClass->getConstants();
        $seen = [];
        foreach ($reflectionClass->getConstants() as $name => $constantValue) {
            $reflectionConstant = new \ReflectionClassConstant($workflowClass, $name);
            foreach ($reflectionConstant->getAttributes() as $attribute) {
                $instance = $attribute->newInstance();
                assert($reflectionConstant->getValue() == $constantValue);
                switch ($instance::class) {
                    case Place::class:
                        // check for initial
                        if ($instance->initial) {
                            $initial = $constantValue;
                        }
                        $seen[] = $name;
                        $workflow->place()->name($constantValue) // the name of the place is the value of the constant
                        ->metadata($instance->metadata);
                        break;
                    case Transition::class:
                        $workflow->transition()
                            ->name($constantValue)
                            ->from($instance->from)
                            ->to($instance->to)
                            ->metadata($instance->metadata);
                        break;
                }
            }
        }

        // shortcut to add all places of a certain pattern, if not already seen
        foreach ($constants as $name => $constantValue) {
            if (preg_match('/PLACE_/', $name)) {

                if (!in_array($name, $seen)) {
                    $workflow->place()->name($constantValue);
                    // @todo: look at attributes
                    if (empty($initial)) {
                        $initial = $constantValue;
                    }
                }
            }
        }
        $workflow->initialMarking($initial);
    }
}
