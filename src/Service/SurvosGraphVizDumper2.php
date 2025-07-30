<?php

namespace Survos\WorkflowBundle\Service;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Dumper\GraphvizDumper;
use Symfony\Component\Workflow\Marking;

// with help from ChatGPT but too many issues to use yet.
class SurvosGraphVizDumper2 extends GraphvizDumper
{
    private array $dumpOptions = [];

    protected static array $defaultOptions = [
        'graph'      => ['ratio' => 'compress', 'rankdir' => 'LR'],
        'node'       => [
            'fontsize'  => '10',
            'fontname'  => 'Helvetica',
            'color'     => '#333333',
            'style'     => 'rounded,filled',
            'fixedsize' => 'false',
            'width'     => '1',
        ],
        'edge'       => [
            'fontsize'  => '8',
            'fontname'  => 'Helvetica',
            'color'     => '#333333',
            'arrowhead' => 'normal',
            'arrowsize' => '0.5',
        ],
        'place'      => ['shape' => 'oval', 'fillcolor' => 'white',       'fontcolor' => '#000000'],
        'transition' => ['shape' => 'box',   'fillcolor' => 'lightyellow', 'fontcolor' => '#000000'],
    ];

    public function dump(Definition $definition, ?Marking $marking = null, array $options = []): string
    {
        // Merge provided options with defaults
        $options = array_replace_recursive(self::$defaultOptions, $options);
        $this->dumpOptions = $options;
        $withMetadata     = $options['with-metadata'] ?? true;

        $places      = $this->findPlaces($definition, $withMetadata, $marking);
        $transitions = $this->findTransitions($definition, $withMetadata);
        $edges       = $this->findEdges($definition);
        $label       = $this->formatLabel($definition, $withMetadata, $options);

        // Guard against missing keys
        $placeStyle      = array_merge($options['node'] ?? [], $options['place'] ?? []);
        $transitionStyle = array_merge($options['node'] ?? [], $options['transition'] ?? []);

        $dot  = $this->startDot($options, $label)
            // Places cluster
            . "  subgraph cluster_places {\n"
            . "    node [" . $this->addOptions($placeStyle) . "];\n"
            . $this->addPlaces($places, $withMetadata)
            . "  }\n"
            // Transitions cluster
            . "  subgraph cluster_transitions {\n"
            . "    node [" . $this->addOptions($transitionStyle) . "];\n"
            . $this->addTransitions($transitions, $withMetadata)
            . "  }\n"
            // Edges and close
            . $this->addEdges($edges)
            . $this->endDot();

        return $dot;
    }

    protected function findPlaces(Definition $definition, bool $withMetadata, ?Marking $marking = null): array
    {
        $opts   = $this->dumpOptions;
        $store  = $definition->getMetadataStore();
        $places = [];

        foreach ($definition->getPlaces() as $place) {
            $attrs = [
                'shape'     => $opts['place']['shape'],
                'style'     => 'filled',
                'fillcolor' => $store->getMetadata('bg_color', $place) ?? $opts['place']['fillcolor'],
                'fontcolor' => $opts['place']['fontcolor'],
            ];
            if (in_array($place, $definition->getInitialPlaces(), true)) {
                $attrs['penwidth'] = '2';
            }
            if ($marking?->has($place)) {
                $attrs['color']    = '#d9534f';
                $attrs['penwidth'] = '2';
            }

            // High-contrast for terminal places
            $hasOutgoing = false;
            foreach ($definition->getTransitions() as $t) {
                if (in_array($place, $t->getFroms(), true)) {
                    $hasOutgoing = true;
                    break;
                }
            }
            if (!$hasOutgoing) {
                $attrs['fillcolor'] = '#2C3E50';
                $attrs['fontcolor'] = '#ffffff';
            }

            if ($withMetadata) {
                $attrs['metadata'] = $store->getPlaceMetadata($place);
            }

            $label = $store->getMetadata('label', $place) ?? $place;
            unset($attrs['metadata']['label']);

            $places[$place] = ['attributes' => $attrs, 'label' => $label];
        }

        return $places;
    }

    protected function findTransitions(Definition $definition, bool $withMetadata): array
    {
        $opts        = $this->dumpOptions;
        $store       = $definition->getMetadataStore();
        $transitions = [];

        foreach ($definition->getTransitions() as $i => $transition) {
            $attrs = [
                'shape'     => $opts['transition']['shape'],
                'style'     => 'filled',
                'fillcolor' => $store->getMetadata('bg_color', $transition) ?? $opts['transition']['fillcolor'],
                'fontcolor' => $opts['transition']['fontcolor'],
            ];
            $name     = $store->getMetadata('label', $transition) ?? $transition->getName();
            $metadata = $withMetadata ? $store->getTransitionMetadata($transition) : [];
            unset($metadata['label']);

            $transitions[$i] = ['attributes' => $attrs, 'name' => $name, 'metadata' => $metadata];
        }

        return $transitions;
    }

    protected function addPlaces(array $places, float $withMetadata): string
    {
        $code = '';
        foreach ($places as $id => $place) {
            $dotId = $this->dotize($id);
            if ($withMetadata && !empty($place['attributes']['metadata'])) {
                $label = sprintf(
                    '<<B>%s</B>%s>',
                    $this->escape($place['label']),
                    $this->addMetadata($place['attributes']['metadata'])
                );
                unset($place['attributes']['metadata']);
            } else {
                $label = sprintf('"%s"', $this->escape($place['label']));
            }

            $code .= sprintf(
                "    place_%s [label=%s %s];\n",
                $dotId,
                $label,
                $this->addAttributes($place['attributes'])
            );
        }

        return $code;
    }

    protected function addTransitions(array $transitions, bool $withMetadata): string
    {
        $code = '';
        foreach ($transitions as $i => $tran) {
            $dotId = $this->dotize((string)$i);
            if ($withMetadata && !empty($tran['metadata'])) {
                $label = sprintf(
                    '<<B>%s</B>%s>',
                    $this->escape($tran['name']),
                    $this->addMetadata($tran['metadata'])
                );
            } else {
                $label = sprintf('"%s"', $this->escape($tran['name']));
            }

            $code .= sprintf(
                "    transition_%s [label=%s %s];\n",
                $dotId,
                $label,
                $this->addAttributes($tran['attributes'])
            );
        }

        return $code;
    }

    protected function addEdges(array $edges): string
    {
        $code = '';
        foreach ($edges as $edge) {
            $from = $this->dotize($edge['from']);
            $to   = $this->dotize($edge['to']);
            $num  = $edge['transition']??null;

            $code .= sprintf("  place_%s -> transition_%s [style=\"solid\"];\n", $from, $num);
            $code .= sprintf("  transition_%s -> place_%s [style=\"solid\"];\n", $num, $to);
        }

        return $code;
    }

    protected function startDot(array $options, string $label): string
    {
        // Graph-level attributes
        $lines = '';
        foreach ($options['graph'] as $k => $v) {
            $lines .= sprintf("  %s=\"%s\";\n", $k, $v);
        }

        $labelLine = $label && $label !== '<>'
            ? sprintf("  label=%s;\n", $label)
            : '';

        $nodeOpts = $this->addOptions($options['node']);
        $edgeOpts = $this->addOptions($options['edge']);

        return "digraph workflow {\n"
            . $lines
            . $labelLine
            . "  node [{$nodeOpts}];\n"
            . "  edge [{$edgeOpts}];\n\n";
    }

    protected function endDot(): string
    {
        return "}\n";
    }

    protected function formatLabel(Definition $definition, string $withMetadata, array $options): string
    {
        $current = $options['label'] ?? '';
        $meta    = $definition->getMetadataStore()->getWorkflowMetadata();

        if ('false' === $withMetadata) {
            return sprintf('"%s"', $this->escape($current));
        }

        if ('' === $current) {
            return sprintf("<%s>", $this->addMetadata($meta, false));
        }

        return sprintf(
            "<<B>%s</B>%s>",
            $this->escape($current),
            $this->addMetadata($meta)
        );
    }

    protected function escape(string|bool|null $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $escaped = htmlspecialchars($value, ENT_QUOTES);
        return addslashes(wordwrap($escaped, 20, "<BR/>", true));
    }

    protected function addAttributes(array $attrs): string
    {
        $parts = [];
        foreach ($attrs as $k => $v) {
            $parts[] = sprintf('%s="%s"', $k, $this->escape((string)$v));
        }
        return $parts ? ' '.implode(' ', $parts) : '';
    }

    protected function addOptions(array $opts): string
    {
        $parts = [];
        foreach ($opts as $k => $v) {
            $parts[] = sprintf('%s="%s"', $k, $v);
        }
        return implode(' ', $parts);
    }

    protected function addMetadata(array $metadata, bool $lineBreak = true): string
    {
        $out   = [];
        $first = !$lineBreak;
        foreach ($metadata as $k => $v) {
            if ('bg_color' === $k) {
                continue;
            }
            $prefix = $first ? '' : '<BR/>';
            if (is_array($v)) {
                $v = implode(', ', $v);
            }
            switch ($k) {
                case 'description':
                    $out[] = sprintf('%s<I>%s</I>', $prefix, $this->escape((string)$v));
                    break;
                case 'guard':
                    $out[] = sprintf('%s<U>%s</U>', $prefix, $this->escape((string)$v));
                    break;
                default:
                    $out[] = sprintf('%s%s: %s', $prefix, $this->escape($k), $this->escape((string)$v));
            }
            $first = false;
        }
        return implode('', $out);
    }

    protected function dotize(string $id): string
    {
        return preg_replace('/[^A-Za-z0-9_]/', '_', $id) ?: '';
    }
}
