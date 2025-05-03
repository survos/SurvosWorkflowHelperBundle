<?php
namespace Survos\WorkflowBundle\Service;

// worth reading: https://sketchviz.com/flowcharts-in-graphviz

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Dumper\GraphvizDumper;
use Symfony\Component\Workflow\Marking;

class SurvosGraphVizDumper extends GraphvizDumper
{

    private string $placeShape = 'oval';
    private string $transitionShape = 'box';

    protected static array $defaultOptions = [
        'graph' => ['ratio' => 'compress', 'rankdir' => 'LR'],
        'node' => ['fontsize' => '8', 'fontname' => 'Arial', 'color' => '#333333',
            'fillcolor' => 'lightgreen',
            'fixedsize' => 'false', 'width' => '1'],
        'edge' => ['fontsize' => '7', 'fontname' => 'Arial', 'color' => '#333333', 'arrowhead' => 'normal', 'arrowsize' => '0.5'],
    ];

    public function dump(Definition $definition, ?Marking $marking = null, array $options = []): string
    {
        $withMetadata = $options['with-metadata'] ?? true;
        $withMetadata = true;

        $places = $this->findPlaces($definition, $withMetadata, $marking);
        $transitions = $this->findTransitions($definition, $withMetadata);
        $edges = $this->findEdges($definition);

        $options = array_replace_recursive(self::$defaultOptions, $options);

        $label = $this->formatLabel($definition, $withMetadata, $options);

        return $this->startDot($options, $label)
            .$this->addPlaces($places, $withMetadata)
            .$this->addTransitions($transitions, $withMetadata)
            .$this->addEdges($edges)
            .$this->endDot();
    }


    /**
     * @internal
     */
    protected function findPlaces(Definition $definition, bool $withMetadata, ?Marking $marking = null): array
    {
        $workflowMetadata = $definition->getMetadataStore();

        $places = [];

        foreach ($definition->getPlaces() as $place) {
            $attributes = [];
            $attributes['fillcolor'] = 'lightgreen';
            if (\in_array($place, $definition->getInitialPlaces(), true)) {
                $attributes['style'] = 'filled';
            }
            if ($marking?->has($place)) {
                $attributes['color'] = '#FF0000';
                $attributes['shape'] = 'doublecircle';
            }
            $backgroundColor = $workflowMetadata->getMetadata('bg_color', $place);
            if (null !== $backgroundColor) {
                $attributes['style'] = 'filled';
                $attributes['fillcolor'] = $backgroundColor;
            } else {
                $attributes['style'] = 'filled';
                $attributes['fillcolor'] = 'lightgreen';

            }
            if ($withMetadata) {
                $attributes['metadata'] = $workflowMetadata->getPlaceMetadata($place);
            }
            $label = $workflowMetadata->getMetadata('label', $place);
            if (null !== $label) {
                $attributes['name'] = $label;
                if ($withMetadata) {
                    // Don't include label in metadata if already used as name
                    unset($attributes['metadata']['label']);
                }
            }
            $places[$place] = [
                'attributes' => $attributes,
            ];
        }

        return $places;
    }

    /**
     * @internal
     */
    protected function findTransitions(Definition $definition, bool $withMetadata): array
    {
        $workflowMetadata = $definition->getMetadataStore();

        $transitions = [];

        foreach ($definition->getTransitions() as $transition) {
            $attributes = ['shape' => $this->transitionShape, 'regular' => true];

            $backgroundColor = $workflowMetadata->getMetadata('bg_color', $transition);
            if (null !== $backgroundColor) {
                $attributes['style'] = 'filled';
                $attributes['fillcolor'] = $backgroundColor;
            }
            $name = $workflowMetadata->getMetadata('label', $transition) ?? $transition->getName();


            $metadata = [];
            if ($withMetadata) {
                $metadata = $workflowMetadata->getTransitionMetadata($transition);
                unset($metadata['label']);
            }

            $transitions[] = [
                'attributes' => $attributes,
                'name' => $name,
                'metadata' => $metadata,
            ];
        }

        return $transitions;
    }

    /**
     * @internal
     */
    protected function addPlaces(array $places, float $withMetadata): string
    {
        $code = '';

        foreach ($places as $id => $place) {
            if (isset($place['attributes']['name'])) {
                $placeName = $place['attributes']['name'];
                unset($place['attributes']['name']);
            } else {
                $placeName = $id;
            }

            if ($withMetadata) {
                $escapedLabel = \sprintf('<<B>%s</B>%s>', $this->escape($placeName), $this->addMetadata($place['attributes']['metadata']));
                // Don't include metadata in default attributes used to format the place
                unset($place['attributes']['metadata']);
            } else {
                $escapedLabel = \sprintf('"%s"', $this->escape($placeName));
            }

            $code .= \sprintf("  place_%s [label=%s, shape=%s%s];\n", $this->dotize($id), $escapedLabel,
                $this->placeShape,
                $this->addAttributes($place['attributes']));
        }

        return $code;
    }

    /**
     * @internal
     */
    protected function addTransitions(array $transitions, bool $withMetadata): string
    {
        $code = '';

        foreach ($transitions as $i => $place) {
            if ($withMetadata) {
//                $escapedLabel = \sprintf('<<B>%s</B><SUP>1</SUP>%s>', $this->escape($place['name']), $this->addMetadata($place['metadata']));
                $escapedLabel = \sprintf('<<B>%s</B>%s>', $this->escape($place['name']), $this->addMetadata($place['metadata']));
            } else {
                $escapedLabel = '"'.$this->escape($place['name']).'"';
            }

            $code .= \sprintf("  transition_%s [label=%s,%s];\n",
                $this->dotize($i),
                $escapedLabel, $this->addAttributes($place['attributes']));
        }

        return $code;
    }

    /**
     * @internal
     */
    protected function findEdges(Definition $definition): array
    {
        $workflowMetadata = $definition->getMetadataStore();

        $dotEdges = [];

        foreach ($definition->getTransitions() as $i => $transition) {
            $transitionName = $workflowMetadata->getMetadata('label', $transition) ?? $transition->getName();

            foreach ($transition->getFroms() as $from) {
                $dotEdges[] = [
                    'from' => $from,
                    'to' => $transitionName,
                    'direction' => 'from',
                    'transition_number' => $i, // $from . $i,
                ];
            }
            foreach ($transition->getTos() as $to) {
                $dotEdges[] = [
                    'from' => $transitionName,
                    'to' => $to,
                    'direction' => 'to',
                    'transition_number' => $i,
                ];
            }
        }

        return $dotEdges;
    }

    /**
     * @internal
     */
    protected function addEdges(array $edges): string
    {
        $code = '';

        foreach ($edges as $edge) {
            if ('from' === $edge['direction']) {
                $code .= \sprintf('  place_%s -> transition_%s [style="solid", comment="%s"];' . "\n",
                    $this->dotize($edge['from']),
                    $this->dotize($edge['transition_number']),
                    $edge['from']
                );
            } else {
                $code .= \sprintf("  transition_%s -> place_%s [style=\"solid\"];\n",
                    $this->dotize($edge['transition_number']),
                    $this->dotize($edge['to'])
                );
            }
        }

        return $code;
    }

    /**
     * @internal
     */
    protected function startDot(array $options, string $label): string
    {
        return \sprintf("digraph workflow {\n  %s%s\n  node [%s];\n  edge [%s];\n\n",
            $this->addOptions($options['graph']),
            '""' !== $label && '<>' !== $label ? \sprintf(' label=%s', $label) : '',
            $this->addOptions($options['node']),
            $this->addOptions($options['edge'])
        );
    }

    /**
     * @internal
     */
    protected function endDot(): string
    {
        return "}\n";
    }

    /**
     * @internal
     */
    protected function dotize(string $id): string
    {
        // inject slugger?
        return $id;
        return hash('sha1', $id);
    }

    /**
     * @internal
     */
    protected function escape(string|bool|null $value): string
    {
        if (is_bool($value)) {
            return '';
        }

        if (is_string($value)) {
            $value = htmlspecialchars($value);
            $value = wordwrap($value, 20, "<BR/>", true);
        }
        $value =  \is_bool($value) ? ($value ? '1' : '0') : addslashes($value);
        return $value;
    }

    /**
     * @internal
     */
    protected function addAttributes(array $attributes): string
    {
        $code = [];

        foreach ($attributes as $k => $v) {
            $code[] = \sprintf('%s="%s"', $k, $this->escape($v));
        }

        return $code ? ' '.implode(' ', $code) : '';
    }

    /**
     * Handles the label of the graph depending on whether a label was set in CLI,
     * if metadata should be included and if there are any.
     *
     * The produced label must be escaped.
     *
     * @internal
     */
    protected function formatLabel(Definition $definition, string $withMetadata, array $options): string
    {
        $currentLabel = $options['label'] ?? '';
        $withMetadata = true;

//        if (!$withMetadata) {
//            // Only currentLabel to handle. If null, will be translated to empty string
//            return \sprintf('"%s"', $this->escape($currentLabel));
//        }
        $workflowMetadata = $definition->getMetadataStore()->getWorkflowMetadata();

        if ('' === $currentLabel) {
            // Only metadata to handle
            return \sprintf('<%s>', $this->addMetadata($workflowMetadata, false));
        }

        // currentLabel and metadata to handle
        return \sprintf('<<B>%s</B>%s>', $this->escape($currentLabel), $this->addMetadata($workflowMetadata));
    }

    private function addOptions(array $options): string
    {
        $code = [];

        foreach ($options as $k => $v) {
            $code[] = \sprintf('%s="%s"', $k, $v);
        }

        return implode(' ', $code);
    }

    /**
     * @param bool $lineBreakFirstIfNotEmpty Whether to add a separator in the first place when metadata is not empty
     */
    private function addMetadata(array $metadata, bool $lineBreakFirstIfNotEmpty = true): string
    {
        $code = [];

        $skipSeparator = !$lineBreakFirstIfNotEmpty;

        foreach ($metadata as $key => $value) {
            if ($skipSeparator) {
                $code[] = \sprintf('%s: %s', $this->escape($key), $this->escape($value));
                $skipSeparator = false;
            } else {
                switch ($key) {
                    case 'guard':

                        $value = preg_replace('/\.(is|has)/', '.', $value);
                        $value = str_replace('()', '', $value);
                        $value = str_replace('subject.', '', $value);
                        if (preg_match('|is_granted\(" ?(.*?)" ?\)|', $value, $matches)) {
                            $value = str_replace($matches[0], $matches[1], $value);
//                            $value =
//                            dd($matches, $value);
                        }
//                        dump($value);
                        $code[] = \sprintf('%s<U>%s</U>', '<BR/>',
                            $this->escape($value));
                        break;
                    case 'description':
                        if ($value) {
                            $code[] = \sprintf('%s<I>%s</I>', '<BR/>',
                                $this->escape($value));
                        }
                        break;
                    case 'bg_color':
                        // ignore, since the node is going to be that color
                        break;
                    default:
                        $code[] = \sprintf('%s%s: %s', '<BR/>', $this->escape($key), $this->escape($value));

                }
            }
        }

        return $code ? implode('', $code) : '';
    }


}
