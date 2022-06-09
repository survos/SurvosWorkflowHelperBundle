<?php
declare(strict_types=1);


namespace Survos\WorkflowBundle\Traits;

use Survos\BaseBundle\Entity\SurvosBaseEntity;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

trait TransitionsBuilderTrait
{

    public function addTransitions(WorkflowInterface $workflow, SurvosBaseEntity $entity, FormBuilderInterface $builder): void
    {
        $metadataStore = $workflow->getMetadataStore();
        /** @var Transition $transition */
        foreach ($workflow->getEnabledTransitions($entity) as $transition) {


            // could add the colors to the transitions in extra!
            $transitionMetadata = $metadataStore->getTransitionMetadata($transition);
            $builder
                ->add($transition->getName(), SubmitType::class, [
                    'attr' => [
                        'style' => 'float: left',
                        'class' => sprintf("btn btn-sm float-left transition transition-%s %s",
                            $transition->getName(),
                            $metadataStore->getTransitionMetadata($transition)['class'] ?? '')]
                ]);
            if (($transitionMetadata['frequency'] ?? 'low') <> 'low') {
            }
        }
        try {
        } catch (\Exception $e) {

        }

        // really we should be checking if new or not, can't save w/o transition if new.  Or maybe we can.  Hmm.
            $builder->add('save_without_transitions', SubmitType::class, [
                'label' => "Update (no transition)",
                'attr' => [
                    'style' => 'float: right',
                    'class' => "btn btn-sm btn-primary float-right"
                ]
            ]);
        if ($entity->getId()) {
        }
    }

}
