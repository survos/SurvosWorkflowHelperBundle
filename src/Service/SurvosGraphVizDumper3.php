<?php

namespace Survos\WorkflowBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Dumper\GraphvizDumper;
use Symfony\Component\Workflow\Marking;

class SurvosGraphVizDumper3 extends GraphvizDumper
{
    private string $placeShape = 'oval';
    private string $transitionShape = 'box';

    protected static array $defaultOptions = [
        'graph' => ['ratio' => 'compress', 'rankdir' => 'TB'],
        'node'  => [
            'fontsize'  => '8',
            'fontname'  => 'Arial',
            'color'     => 'lightBlue',
            'style'     => 'filled',
            'fixedsize' => 'false',
            'width'     => '3',
            'height'    => '1.2',
            'margin'    => '0.3,0.2'
        ],
        'edge'  => [
            'fontsize'  => '7',
            'fontname'  => 'Arial',
            'color'     => '#333333',
            'arrowhead' => 'normal',
            'arrowsize' => '0.5',
        ],
        'place'      => ['shape' => 'oval', 'fillcolor' => '#FFD966'],
        'transition' => ['shape' => 'box',  'fillcolor' => 'lightyellow'],
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
            . $this->addPlaces($places, $withMetadata)
            . $this->addTransitions($transitions, $withMetadata)
            . $this->addEdges($edges)
            . $this->endDot();
    }

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

            $metadataHtml = $withMetadata ? $this->addMetadataTable($placeName, $place['attributes']['metadata'] ?? []) : $this->escape($placeName);

            if ($place['attributes']['metadata']??false) {
                $place['attributes']['metadata'] = json_encode($place['attributes']['metadata']);
            }
//            if ($place['attributes']['metadata']['next']??false)
//            {
//                $place['attributes']['metadata']['next'] = join(',', $place['attributes']['metadata']['next']);
//                dd($place);
//            }
            $code .= sprintf(
                "  place_%s [label=<%s>, shape=%s%s];\n",
                $this->dotize($id),
                $metadataHtml,
                $this->placeShape,
                'attributes',
//                $this->addAttributes($place['attributes'])
            );
        }

        return $code;
    }

    protected function addTransitions(array $transitions, bool $withMetadata): string
    {
        $code = '';

        foreach ($transitions as $i => $transition) {
            $name = $transition['name'];
            $metadata = $transition['metadata'];
            $attributes = $transition['attributes'];

            $label = $withMetadata ? $this->addMetadataTable($name, $metadata) : $this->escape($name);

            $code .= sprintf(
                "  transition_%s [label=<%s>, shape=%s%s];\n",
                $this->dotize($i),
                $label,
                $this->transitionShape,
                $this->addAttributes($attributes)
            );
        }

        return $code;
    }

    private function addMetadataTable(string $title, array $metadata): string
    {
        $rows = [sprintf('<TR><TD><B>%s</B></TD></TR>', $this->escape($title))];

        foreach ($metadata as $key => $value) {
            if ($key === 'bg_color') {
                continue;
            }
//            if (is_array($value)) {
//                dd($value, $metadata);
//            }
            $escapedValue = is_array($value)
                ? $this->escape(implode(', ', array_map('strval', $value)))
                : $this->escape($value);
            $escapedValue = strip_tags($escapedValue);
            str_contains($escapedValue, '<n>') && dd($escapedValue);

//            $escapedValue = $this->escape($value);
            $rows[] = sprintf('<TR><TD><FONT POINT-SIZE="7">%s%s</FONT></TD></TR>', $key === 'description' ? '<I>' : '', $escapedValue . ($key === 'description' ? '</I>' : ''));
        }

        return sprintf('<TABLE BORDER="0" CELLBORDER="0" CELLSPACING="1">%s</TABLE>', implode('', $rows));
    }
}
