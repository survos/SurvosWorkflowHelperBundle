<?php

namespace Survos\WorkflowBundle\Traits;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Workflow\Transition;

trait MarkingTrait
{
    /**
     * @var string
     * @ORM\Column(type="string", length=32, nullable=true)
     */
    private ?string $marking = null; // self::INITIAL_MARKING;

    private \DateTime $lastTransitionTime;
    private array $enabledTransitions = [];

    /**
     * @ORM\Column(name="marking_history_json", type="json_array", columnDefinition="JSON", nullable=true)
     * @var array
    private $markingHistory;
     */

    /**
     * @return string
     */
    public function getMarking(): ?string
    {
        return $this->marking;
    }

    /**
     *   Note : type must be 'method', see https://symfony.com/blog/new-in-symfony-4-3-workflow-improvements#added-a-context-to-workflow-apply
     *   get the context with $event->getContext();
     * @param string $marking
     * @return self
     */
    public function setMarking(?string $marking, $context=[])
    {
        $this->marking = $marking;

        return $this;
    }

    /**
     * @return string
     */
    public function getMarkingDisplay()
    {
        return $this->marking; // go through trans?  at least titleCase?
    }

    /**
     * @param string $marking
     * @return bool
     */
    public function hasMarking(string $marking) : bool
    {
        return $this->marking == $marking;
    }

    /**
     * @return array
     */
    public function getMarkingHistory()
    {
        return $this->markingHistory ?? [];
    }

    /**
     * @param array $history
     * @return self
     */
    public function setMarkingHistory(array $history)
    {
        $this->markingHistory = [];
        foreach ($history as $item) {
            $this->addMarkingHistoryEvent($item);
        }

        return $this;
    }

    public function addMarkingHistoryComment(?String $comment) {
        if ($comment) {
            $history = $this->getMarkingHistory();
            if ($lastEvent = array_pop($history)) {
                $lastEvent['comment'] = $comment;
                array_push($history, $lastEvent);
                $this->setMarkingHistory($history);
            }
        }
    }

    /**
     * @param array $data
     * @deprecated
     * @return self
     */
    public function addMarkingHistoryEvent($data)
    {
        $resolver = new OptionsResolver();
        $resolver->setRequired([
            'timestamp', 'transition', 'froms', 'tos', 'loggedInMemberId'
        ])
            ->setDefaults([
                'comment' => ''
            ])
        ;
        $data = $resolver->resolve($data);
        $timestamp = $data['timestamp'];
        if ($timestamp instanceof \DateTimeInterface) {
            $data['timestamp'] = $timestamp->format('c');
        }
        $this->markingHistory[] = $data;

        return $this;
    }

    /**
     * @return string
     */
    public function getMarkingHistoryDisplay()
    {
        $text = '';
        foreach ($this->markingHistory as $h) {
            if ($text) {
                $text .= "\n";
            }
            $text .= sprintf(
                '%s (%s->%s): %s',
                $h['transition'],
                implode(',', $h['froms']),
                implode(',', $h['tos']),
                preg_replace('/\+00:?00$/', 'Z', $h['timestamp'])
            );
            if ($h['comment']) {
                $text .= ' [' . $h['comment'] . ']';
            }
        }

        return $text;
    }

    /**
     * Set lastTransitionTime
     *
     * @deprecated
     * @param \DateTime $lastTransitionTime
     * @return self
     *
     */
    public function setLastTransitionTime(?\DateTime $lastTransitionTime)
    {
        $this->lastTransitionTime = $lastTransitionTime;

        return $this;
    }

    /**
     * Get lastTransitionTime
     *
     * @deprecated
     * @return \DateTime
     */
    public function getLastTransitionTime(): ?\DateTime
    {
        return $this->lastTransitionTime;
    }

    public function getWorkflowName()
    {
        $name = (new \ReflectionClass($this))->getShortName();
        return strtolower($name);
        return get_class($this);
        // dd($name);
        dd( get_class($this));
    }

    public function setEnabledTransitions(array $enabledTransitions) {
        // set by the doctrine postLoad listener
        $this->enabledTransitions = $enabledTransitions;
        return $this;
    }

    public function getEnabledTransitions(): ?array {
        return $this->enabledTransitions ?: [];
    }

    public function getEnabledTransitionCodes() {
        return array_map( fn(Transition $transition) => $transition->getName(), $this->getEnabledTransitions());
    }

}
